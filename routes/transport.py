from flask import Blueprint, request, redirect, url_for, render_template, session
from utils.database import get_db_connection
from routes.patients import get_patients_by_day
from routes.insurances import get_insurances

transport_bp = Blueprint("transport", __name__)

@transport_bp.route('/transport', methods=['GET'])
def transport():
    from math import radians, sin, cos, sqrt, atan2
    import requests

    month = session.get("month")
    if not month:
        return "Nie je vybraný mesiac", 400

    month_number = month.get("mesiac")
    year_number = month.get("rok")
    nurse_id = session.get("nurse", {}).get("id")

    if not month_number or not year_number or not nurse_id:
        return "Chýbajú údeje o mesiaci, roku alebo sestre", 400

    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("""
        SELECT
            d.datum AS datum,
            p.id AS pacient_id,
            p.meno AS pacient_meno,
            p.rodne_cislo,
            po.kod AS kod_poistovne,
            diag.kod AS diagnoza,
            car.evc,
            a.ulica || ', ' || a.mesto AS start_adresa,
            p.adresa AS end_adresa,
            l.pzs AS odosielajuci_lekar,
            l.pzs AS odosielajuca_pzs,
            a.kod AS ambulancia
        FROM den_pacient dp
        LEFT JOIN dni d ON d.id = dp.den_id
        LEFT JOIN pacienti p ON p.id = dp.pacient_id
        LEFT JOIN sestry s ON s.id = p.sestra
        LEFT JOIN adosky a ON a.id = s.ados
        LEFT JOIN diagnozy diag ON diag.id = p.diagnoza
        LEFT JOIN poistovne po ON po.id = p.poistovna
        LEFT JOIN auta car ON car.id = s.vozidlo
        LEFT JOIN doktori l ON l.id = p.odosielatel
        WHERE strftime('%m', d.datum) = ? AND strftime('%Y', d.datum) = ? AND p.sestra = ?
        ORDER BY d.datum, dp.vysetrenie
    """, (
        f"{int(month_number):02}",
        str(year_number),
        nurse_id
    ))

    rows = [dict(row) for row in cursor.fetchall()]

    # Haversine distance for closeness check
    def haversine(lat1, lon1, lat2, lon2):
        R = 6371000
        dlat = radians(lat2 - lat1)
        dlon = radians(lon2 - lon1)
        a = sin(dlat/2)**2 + cos(radians(lat1)) * cos(radians(lat2)) * sin(dlon/2)**2
        return R * 2 * atan2(sqrt(a), sqrt(1 - a))

    def geocode_address(address):
        try:
            res = requests.get("https://api.openrouteservice.org/geocode/search", params={
                "api_key": "5b3ce3597851110001cf624834beac90e22b4e7aae5bb2e22e93aa5d",
                "text": address,
                "size": 1
            })
            coords = res.json()["features"][0]["geometry"]["coordinates"]
            return coords[1], coords[0]  # lat, lon
        except Exception as e:
            print(f"Geocode error: {e}")
            return None, None

    def get_distance_km(start, end):
        try:
            res = requests.post("https://api.openrouteservice.org/v2/directions/driving-car/json", 
                headers={
                    "Authorization": "5b3ce3597851110001cf624834beac90e22b4e7aae5bb2e22e93aa5d",
                    "Content-Type": "application/json"
                },
                json={"coordinates": [[start[1], start[0]], [end[1], end[0]]]})
            return round(res.json()["routes"][0]["summary"]["distance"] / 1000, 2)
        except Exception as e:
            print(f"Distance error: {e}")
            return 0

    # Group addresses by date and avoid duplicates within 10m radius
    processed_coords = {}
    for row in rows:
        row["km"] = 0
        date = row["datum"]
        start_lat, start_lon = geocode_address(row["start_adresa"])
        end_lat, end_lon = geocode_address(row["end_adresa"])

        if not (start_lat and end_lat):
            continue

        if date not in processed_coords:
            processed_coords[date] = []

        # Skip if similar coordinate already exists
        if any(haversine(end_lat, end_lon, lat, lon) < 10 for lat, lon in processed_coords[date]):
            continue

        processed_coords[date].append((end_lat, end_lon))
        row["km"] = get_distance_km((start_lat, start_lon), (end_lat, end_lon))

    return render_template("transport/transport.html", data=rows)




