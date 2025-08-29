from __future__ import annotations

from concurrent.futures import ThreadPoolExecutor
import threading
from queue import Queue
from typing import Dict, List, Tuple, Any, Optional
import random
from datetime import datetime, timedelta

from flask import session

from utils.database import get_db_connection
from utils.geocode import geocode_address
from utils.roads_manager import Road_manager

# Optional logging helpers (as in your codebase)
from easy.message import inform, success, failed

# ------------------------------
# Thread-safe DB update queue
# ------------------------------
db_queue: "Queue[Optional[Tuple[int, int, datetime, datetime]]]" = Queue()


def db_worker() -> None:
    """
    Background worker that applies time assignments to DB.
    Each queue item is a tuple: (den_id, pacient_id, vysetrenie_time, vypis_time).
    Send None to stop the worker.
    """
    conn = get_db_connection()
    cur = conn.cursor()

    try:
        while True:
            task = db_queue.get()
            if task is None:
                break

            try:
                den_id, pacient_id, vysetrenie_time, vypis_time = task

                cur.execute(
                    """
                    UPDATE den_pacient
                    SET vysetrenie = ?, vypis = ?
                    WHERE den_id = ? AND pacient_id = ?
                    """,
                    (
                        vysetrenie_time.strftime("%H:%M"),
                        vypis_time.strftime("%H:%M"),
                        den_id,
                        pacient_id,
                    ),
                )
                # Commit each update to avoid losing work if something crashes later.
                conn.commit()
            except Exception as e:
                failed(f"[DB worker] Failed to update row: {e}")
    finally:
        try:
            conn.commit()
        except Exception:
            pass
        conn.close()


# ------------------------------
# Helpers
# ------------------------------
def _safe_minutes_from_seconds(sec: Optional[float]) -> float:
    """Convert seconds to minutes safely; coalesce None to 0."""
    if sec is None:
        return 0.0
    try:
        return float(sec) / 60.0
    except Exception:
        return 0.0


def _parse_session_time(key: str, default_val: str) -> datetime:
    """
    Return a datetime parsed from session['month'][key] ('HH:MM').
    We only care about the time-of-day; date part is irrelevant.
    """
    raw = session.get("month", {}).get(key, default_val)
    # Support '8:00' and '08:00'
    fmts = ["%H:%M", "%-H:%M", "%#H:%M"]  # %-H (POSIX), %#H (Windows), %H universal
    for fmt in fmts:
        try:
            return datetime.strptime(raw, fmt)
        except Exception:
            continue
    # Fallback
    return datetime.strptime(default_val, "%H:%M")


# ------------------------------
# Core simulation
# ------------------------------
def simulate_schedule(
    den_id: int,
    patients: List[Dict[str, Any]],
    tsp_path: List[int],
    matrix: List[List[Optional[float]]],
    current_time: datetime,
    vypis_time: datetime,
) -> None:
    """
    Walk the TSP path, add travel + stay + vypis durations,
    and enqueue DB updates (vysetrenie/vypis times) for each visited patient.
    """
    if not tsp_path or len(tsp_path) < 2:
        # Nothing to simulate
        return

    for i in range(len(tsp_path) - 1):
        a, b = tsp_path[i], tsp_path[i + 1]

        travel_time_sec = None
        try:
            travel_time_sec = matrix[a][b]
        except Exception:
            travel_time_sec = None

        travel_minutes = max(0.0, _safe_minutes_from_seconds(travel_time_sec) + random.randint(-5, 5))
        current_time += timedelta(minutes=travel_minutes)

        # Node 0 is the depot/start; only assign times for patient nodes
        if b == 0:
            continue

        # b>0 maps to patients[b-1], since 0 is depot
        patient = patients[b - 1]

        # Stay duration: 8–12 minutes
        stay_minutes = random.randint(8, 12)
        current_time += timedelta(minutes=stay_minutes)

        # Vypis duration: 3–5 minutes (we assign the current vypis_time, then move it forward)
        vypis_time += timedelta(minutes=random.randint(3, 5))
        assigned_vypis_time = vypis_time

        db_queue.put((den_id, patient["id"], current_time, assigned_vypis_time))


def calculate_optimal_day_route_thread(
    day: Any,
    data: Dict[Any, Dict[str, Any]],
    start_point: Tuple[float, float],
    start_vysetrenie: datetime,
    start_vypis: datetime,
) -> None:
    """
    Plan day route for a given 'day' key in `data`.
    Skips gracefully if there are no patients or not enough valid coordinates.
    """
    # Pull inputs
    day_payload = data.get(day, {})
    patients: List[Dict[str, Any]] = day_payload.get("patients", []) or []
    den_id = day_payload.get("den_id")

    if den_id is None:
        inform(f"[day={day}] Missing den_id — skipping.")
        return

    # No patients? Nothing to plan.
    if not patients:
        inform(f"[day={day} den_id={den_id}] No patients — skipping.")
        return

    # Build coordinate list: depot + each patient's (lon, lat), skipping invalid coords
    coords: List[Tuple[float, float]] = [start_point]
    valid_patients: List[Dict[str, Any]] = []

    for p in patients:
        lon = p.get("longitude")
        lat = p.get("latitude")
        if lon is None or lat is None:
            continue
        coords.append((lon, lat))
        valid_patients.append(p)

    if len(coords) < 2:
        inform(f"[day={day} den_id={den_id}] Not enough valid coordinates — skipping.")
        return

    # Request distance matrix (durations in seconds)
    try:
        inform(f"[day={day} den_id={den_id}] Requesting distance matrix…")
        road_manager = Road_manager()  # instantiate per thread to avoid shared-state issues
        res = road_manager.execute_open_route_request(
            operation="distance_matrix",
            locations=coords,
            profile="driving-car",
            metrics=["duration"],
            units="m",  # units affect distance, not duration; harmless to keep
        )
        matrix = res.get("durations")
        if not matrix or len(matrix) < 2:
            inform(f"[day={day} den_id={den_id}] Matrix too small/empty — skipping.")
            return
        success(f"[day={day} den_id={den_id}] Distance matrix OK.")
    except Exception as e:
        failed(f"[day={day} den_id={den_id}] Failed to get distance matrix: {e}")
        return

    # Build weighted complete graph for TSP
    try:
        import networkx as nx

        n = len(coords)
        G = nx.complete_graph(n)
        # Assign weights; coalesce None to 0
        for i in range(n):
            for j in range(i + 1, n):
                w = matrix[i][j] if (i < len(matrix) and j < len(matrix[i])) else None
                if w is None:
                    w = 0
                G[i][j]["weight"] = w

        if G.number_of_edges() == 0:
            inform(f"[day={day} den_id={den_id}] Edge-less graph — skipping.")
            return

        tsp_path: List[int] = nx.approximation.traveling_salesman_problem(G, cycle=True)
        if not tsp_path:
            inform(f"[day={day} den_id={den_id}] Empty TSP path — skipping.")
            return

        # Ensure depot (node 0) is start and end
        if tsp_path[0] != 0:
            start_index = tsp_path.index(0)
            tsp_path = tsp_path[start_index:] + tsp_path[:start_index]
        if tsp_path[-1] != 0:
            tsp_path.append(0)

    except Exception as e:
        failed(f"[day={day} den_id={den_id}] TSP failed: {e}")
        return

    # Simulate schedule using only the valid patients list
    simulate_schedule(
        den_id=den_id,
        patients=valid_patients,
        tsp_path=tsp_path,
        matrix=matrix,
        current_time=start_vysetrenie,
        vypis_time=start_vypis,
    )


def calculate_optimal_day_route(data: Dict[Any, Dict[str, Any]], address: str) -> Dict[str, Any]:
    """
    Entry point to plan routes for all days in 'data'.
    - 'data' is a dict keyed by a day identifier. Each value must include:
        {
          "den_id": <int>,
          "patients": [
              {"id": <int>, "longitude": <float>, "latitude": <float>}, ...
          ]
        }
    - 'address' is the depot address (ADOS) — will be geocoded.
    """
    # Geocode depot
    start_point = geocode_address(address)  # expected (lon, lat)
    inform(f"Start point for calculations: {start_point}")

    # Parse session times (use reasonable defaults if missing)
    current_time = _parse_session_time("start_vysetrenie", "08:00")
    vypis_time = _parse_session_time("start_vypis", "14:00")

    # Start DB worker
    db_thread = threading.Thread(target=db_worker, daemon=True)
    db_thread.start()

    # Run day planners in parallel
    max_workers = max(1, min(31, len(data) or 1))
    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        futures = [
            executor.submit(
                calculate_optimal_day_route_thread,
                day,
                data,
                start_point,
                current_time,
                vypis_time,
            )
            for day in data
        ]

        for f in futures:
            try:
                f.result()
            except Exception as e:
                failed(f"[planner] Day thread crashed: {e}")

    # Stop DB worker
    db_queue.put(None)
    db_thread.join()

    return {"success": True}
