from concurrent.futures import ThreadPoolExecutor
import threading
from easy.message import *
import networkx as nx
import random
from datetime import datetime, timedelta
from flask import session
from utils.database import get_db_connection
from utils.geocode import geocode_address
from utils.roads_manager import Road_manager
from queue import Queue

db_queue = Queue()

def db_worker():
    conn = get_db_connection()
    cur = conn.cursor()

    while True:
        task = db_queue.get()

        if task is None:
            break

        den_id, pacient_id, vysetrenie_time, vypis_time = task

        cur.execute("""
            UPDATE den_pacient
            SET vysetrenie = ?, vypis = ?
            WHERE den_id = ? AND pacient_id = ?
        """, (
            vysetrenie_time.strftime("%H:%M"),
            vypis_time.strftime("%H:%M"),
            den_id,
            pacient_id
        ))

    conn.commit()
    conn.close()

def simulate_schedule(den_id, patients, tsp_path, matrix, current_time, vypis_time):
    for i in range(len(tsp_path) - 1):
        a, b = tsp_path[i], tsp_path[i + 1]
        travel_time_sec = matrix[a][b]

        travel_minutes = max(0, travel_time_sec / 60 + random.randint(-5, 5))
        current_time += timedelta(minutes=travel_minutes)

        if b == 0:
            continue
        else:
            patient = patients[b - 1]

            # Duration of stay: 8 to 12 minutes
            stay_minutes = random.randint(8, 12)
            current_time += timedelta(minutes=stay_minutes)

            # Vypis time: 3 to 5 minutes
            vypis_duration = random.randint(3, 5)
            assigned_vypis_time = vypis_time
            vypis_time += timedelta(minutes=vypis_duration)

            db_queue.put((den_id, patient["id"], current_time, assigned_vypis_time))

def calculate_optimal_day_route_thread(road_manager:Road_manager, day, data, start_point, current_time, vypis_time):
    patients = data[day]["patients"]
    den_id = data[day]["den_id"]

    coords = [start_point] + [(p["longitude"], p["latitude"]) for p in patients]

    try:
        matrix = road_manager.execute_open_route_request(operation="distance_matrix",
            locations=coords,
            profile='driving-car',
            metrics=['duration'],
            units='m'
        )["durations"]
        success("tsp request finished!")

    except Exception as e:
        return

    n = len(coords)
    G = nx.complete_graph(n)
    for i in range(n):
        for j in range(i + 1, n):
            G[i][j]["weight"] = matrix[i][j]

    tsp_path = nx.approximation.traveling_salesman_problem(G, cycle=True)

    if tsp_path[0] != 0:
        start_index = tsp_path.index(0)
        tsp_path = tsp_path[start_index:] + tsp_path[:start_index]
    if tsp_path[-1] != 0:
        tsp_path.append(0)

    simulate_schedule(den_id, patients, tsp_path, matrix, current_time, vypis_time)

def calculate_optimal_day_route(data, address):
    start_point = geocode_address(address)

    current_time = datetime.strptime(session.get("month", {}).get("start_vysetrenie", "8:00"), "%H:%M")
    vypis_time = datetime.strptime(session.get("month", {}).get("start_vypis", "14:00"), "%H:%M")

    db_thread = threading.Thread(target=db_worker)
    db_thread.start()

    road_manager = Road_manager()

    with ThreadPoolExecutor(max_workers=31) as executor:
        futures = [
            executor.submit(calculate_optimal_day_route_thread, road_manager, day, data, start_point, current_time, vypis_time)
            for day in data
        ]
        for f in futures:
            f.result()

    db_queue.put(None)
    db_thread.join()

    return { "success": True }
