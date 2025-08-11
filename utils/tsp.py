import openrouteservice
import networkx as nx
import random
from datetime import datetime, timedelta
from flask import session
from utils.database import get_db_connection
from utils.geocode import geocode_address
from utils.roads_manager import Road_manager

def update_vysetrenie_and_vypis(den_id, pacient_id, vysetrenie_time, vypis_time):
    conn = get_db_connection()
    cur = conn.cursor()
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


def simulate_schedule(day, den_id, patients, tsp_path, matrix):
    start_time_str = session.get("month", {}).get("start_vysetrenie", "8:00")
    vypis_start_str = session.get("month", {}).get("start_vypis", "14:00")

    current_time = datetime.strptime(start_time_str, "%H:%M")
    vypis_time = datetime.strptime(vypis_start_str, "%H:%M")


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

            update_vysetrenie_and_vypis(den_id, patient["id"], current_time, assigned_vypis_time)


def calculate_optimal_day_route(data, address):
    long, lat = geocode_address(address)
    start_point = (long, lat)

    for day in data:
        patients = data[day]["patients"]
        den_id = data[day]["den_id"]

        coords = [start_point] + [(p["longitude"], p["latitude"]) for p in patients]

        try:
             matrix = Road_manager().execute_open_route_request(method_name="distance_matrix",
                locations=coords,
                profile='driving-car',
                metrics=['duration'],
                units='m'
            )["durations"]

        except Exception as e:
            continue

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

        simulate_schedule(day, den_id, patients, tsp_path, matrix)

    return { "success": True }
