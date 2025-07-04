import openrouteservice
import networkx as nx
import random
from datetime import datetime, timedelta
from flask import session
from utils.database import get_db_connection
from utils.geocode import geocode_address

ORS_API_KEYS = [
    "5b3ce3597851110001cf62483e8009ec48d3457d8800432392507809",
    "5b3ce3597851110001cf624834beac90e22b4e7aae5bb2e22e93aa5d"
]

api_call_counter = 0
api_key_index = 0
client = openrouteservice.Client(key=ORS_API_KEYS[api_key_index])


def switch_key_if_needed():
    global api_call_counter, api_key_index, client

    api_call_counter += 1
    if api_call_counter % 20 == 0:
        api_key_index = (api_key_index + 1) % len(ORS_API_KEYS)
        client = openrouteservice.Client(key=ORS_API_KEYS[api_key_index])
        print(f"🔄 Switching to API key {api_key_index + 1}: {ORS_API_KEYS[api_key_index]}")


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
    print(session)
    long, lat = geocode_address(address)
    start_point = (long, lat)

    for day in data:
        patients = data[day]["patients"]
        den_id = data[day]["den_id"]

        coords = [start_point] + [(p["longitude"], p["latitude"]) for p in patients]

        switch_key_if_needed()
        try:
            matrix = client.distance_matrix(
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
