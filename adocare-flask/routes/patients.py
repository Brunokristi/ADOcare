from flask import Blueprint, request, redirect, url_for, render_template, jsonify, session
from models.patient import Patient
import json
from utils.database import get_db_connection
from routes.diagnoses import get_diagnosis
from routes.doctors import get_doctors
from routes.companies import get_companies
from routes.insurances import get_insurances
from utils.roads_manager import Road_manager
from utils.geocode import geocode_address

from flask_login import login_required

patient_bp = Blueprint("patient", __name__)

@patient_bp.route('/patient/create', methods=['GET', 'POST'])
@login_required
def create_patient():
    if request.method == 'POST':
        data = request.form

        address = data['adresa']
        longitude, latitude = geocode_address(address)

        Road_manager().addClient((latitude, longitude))

        conn = get_db_connection()
        conn.execute("""
            INSERT INTO pacienti (
                meno, rodne_cislo, adresa, mesto, poistovna, ados,
                sestra, odosielatel, pohlavie, cislo_dekurzu, diagnoza, longitude, latitude
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""",
            (
                data['meno'], data['rodne_cislo'], data['adresa'], data['mesto'], data['poistovna'],
                data['ados'], data['sestra'], data['odosielatel'],
                data['pohlavie'], data['cislo_dekurzu'], data['diagnoza'], longitude, latitude

            ))
        conn.commit()
        conn.close()
        return redirect(url_for('main.settings'))

    doctors = get_doctors()
    insurances = get_insurances()
    companies = get_companies()
    return render_template("create/patient.html",doctors=doctors, insurances=insurances, companies=companies)

@patient_bp.route('/patient/update/<int:id>', methods=['GET', 'POST'])
@login_required
def update_patient(id):
    if request.method == 'POST':
        data = request.form

        address = data['adresa']
        longitude, latitude = geocode_address(address)

        Road_manager().addClient((latitude, longitude))

        conn = get_db_connection()
        conn.execute("""
            UPDATE pacienti SET
                meno = ?, rodne_cislo = ?, adresa = ?, mesto = ?, poistovna = ?, ados = ?,
                sestra = ?, odosielatel = ?, pohlavie = ?, cislo_dekurzu = ?, diagnoza = ?, longitude = ?, latitude = ?
            WHERE id = ?""",
            (
                data['meno'], data['rodne_cislo'], data['adresa'], data['mesto'], data['poistovna'],
                data['ados'], data['sestra'], data['odosielatel'],
                data['pohlavie'], data['cislo_dekurzu'], data['diagnoza'], longitude, latitude, id
            ))
        conn.commit()
        conn.close()
        return redirect(url_for('patient.list_patients'))

    patient = get_patient(id)
    if not patient:
        return "Pacient nenájdený", 404

    doctors = get_doctors()
    insurances = get_insurances()
    companies = get_companies()
    diagnosis = get_diagnosis(patient.diagnoza)
    return render_template("details/patient.html", patient=patient, insurances=insurances, doctors=doctors, companies=companies, diagnosis=diagnosis)

@patient_bp.route('/patient/delete/<int:id>', methods=['POST'])
@login_required
def delete_patient(id):
    conn = get_db_connection()
    conn.execute("DELETE FROM pacienti WHERE id = ?", (id,))
    conn.commit()
    conn.close()
    return redirect(url_for('patient.list_patients'))

@patient_bp.route('/patient/search')
@login_required
def search_patients():
    query = request.args.get('q', '').strip().lower()
    nurse_id = session.get('nurse', {}).get('id')

    if not nurse_id:
        return jsonify([])

    conn = get_db_connection()
    rows = conn.execute("""
        SELECT * FROM pacienti
        WHERE sestra = ?
        AND (LOWER(meno) LIKE ? OR LOWER(rodne_cislo) LIKE ?)
        ORDER BY meno COLLATE NOCASE;
    """, (nurse_id, f"%{query}%", f"%{query}%")).fetchall()
    conn.close()

    results = [Patient(row).__dict__ for row in rows]
    return jsonify(results)

@patient_bp.route('/patients/list/')
@login_required
def list_patients():
    patients = get_patients()
    return render_template("details/patients.html", patients=patients)

@patient_bp.route('/patients/menu/')
@login_required
def menu():
    day = session.get("month", {}).get("prvy_den")
    patients = get_patients_in_day(day)
    return render_template("dekurzy/menu.html", patients=patients)

@patient_bp.route('/patients/day/<date_str>')
@login_required
def patients_in_day(date_str):
    data = get_patients_in_day(date_str)
    return jsonify(data)

@patient_bp.route('/patients/month/')
@login_required
def get_patients_by_day():
    month_id = session.get("month", {}).get("id")

    if not month_id:
        return {}

    conn = get_db_connection()

    days = conn.execute("""
        SELECT id, datum
        FROM dni
        WHERE mesiac = ?
    """, (month_id,)).fetchall()

    patients_by_day = {}

    for day in days:
        day_id = day["id"]
        date_str = day["datum"]

        patients = conn.execute("""
            SELECT p.id, p.meno, p.longitude, p.latitude
            FROM den_pacient dp
            JOIN pacienti p ON p.id = dp.pacient_id
            WHERE dp.den_id = ?
        """, (day_id,)).fetchall()

        patients_by_day[date_str] = {
            "den_id": day_id,
            "patients": [dict(row) for row in patients]
        }

    conn.close()
    return patients_by_day

def get_patients():
    nurse_id = session.get('nurse', {}).get('id')

    conn = get_db_connection()
    rows = conn.execute("SELECT * FROM pacienti WHERE sestra = ? ORDER BY meno ASC", (nurse_id,)).fetchall()
    conn.close()
    return [Patient(row) for row in rows]

def get_patient(id):
    conn = get_db_connection()
    row = conn.execute("SELECT * FROM pacienti WHERE id = ?", (id,)).fetchone()
    conn.close()
    return Patient(row) if row else None

def get_patients_in_day(date_str):
    nurse_id = session.get("nurse", {}).get("id")
    month_id = session.get("month", {}).get("id")
    if not nurse_id or not month_id or not date_str:
        return []

    conn = get_db_connection()
    rows = conn.execute("""
        SELECT p.*, dp.vysetrenie, dp.vypis, mp.dates_all
        FROM den_pacient dp
        JOIN dni d ON dp.den_id = d.id
        JOIN pacienti p ON dp.pacient_id = p.id
        JOIN mesiac_pacient mp ON p.id = mp.pacient_id
        WHERE mp.mesiac_id = ? AND d.datum = ?
        ORDER BY p.meno
    """, (month_id, date_str)).fetchall()
    conn.close()

    result = []
    for row in rows:
        row_dict = dict(row)
        if "dates_all" in row_dict and row_dict["dates_all"]:
            try:
                row_dict["dates_all"] = json.loads(row_dict["dates_all"])
            except json.JSONDecodeError:
                row_dict["dates_all"] = []
        result.append(row_dict)

    return result

def get_patients_in_month():
    nurse_id = session.get("nurse", {}).get("id")
    month_id = session.get("month", {}).get("id")
    if not nurse_id or not month_id:
        return []

    conn = get_db_connection()
    rows = conn.execute("""
        SELECT p.*
        FROM mesiac_pacient as mp
        JOIN pacienti p ON mp.pacient_id = p.id
        WHERE mp.mesiac_id = ?
        ORDER BY p.meno
    """, (month_id,)).fetchall()
    conn.close()
    return rows

def get_all_patients_info_in_month():
    nurse_id = session.get("nurse", {}).get("id")
    month_id = session.get("month", {}).get("id")
    if not nurse_id or not month_id:
        return []

    conn = get_db_connection()
    cursor = conn.cursor()

    rows = cursor.execute("""
        WITH ranked_data AS (
            SELECT
                mp_inner.*,
                ROW_NUMBER() OVER (
                    PARTITION BY mp_inner.pacient_id
                    ORDER BY m.rok DESC, m.mesiac DESC
                ) AS rn
            FROM mesiac_pacient mp_inner
            JOIN mesiac m ON m.id = mp_inner.mesiac_id
            WHERE mp_inner.pacient_id IN (
                SELECT p.id FROM pacienti p WHERE p.sestra = ?
            )
            AND (
                mp_inner.dates0 IS NOT NULL OR mp_inner.podtext0 IS NOT NULL OR
                mp_inner.dates1 IS NOT NULL OR mp_inner.podtext1 IS NOT NULL OR
                mp_inner.dates2 IS NOT NULL OR mp_inner.podtext2 IS NOT NULL OR
                mp_inner.dates3 IS NOT NULL OR mp_inner.podtext3 IS NOT NULL OR
                mp_inner.dates4 IS NOT NULL OR mp_inner.podtext4 IS NOT NULL OR
                mp_inner.dates5 IS NOT NULL OR mp_inner.podtext5 IS NOT NULL OR
                mp_inner.dates6 IS NOT NULL OR mp_inner.podtext6 IS NOT NULL OR
                mp_inner.dates7 IS NOT NULL OR mp_inner.podtext7 IS NOT NULL
            )
        ),
        latest_data AS (
            SELECT * FROM ranked_data WHERE rn = 1
        )
        SELECT
            p.*,
            mp_current.*,
            ld.dates0 AS latest_dates0,
            ld.podtext0 AS latest_podtext0,
            ld.dates1 AS latest_dates1,
            ld.podtext1 AS latest_podtext1,
            ld.dates2 AS latest_dates2,
            ld.podtext2 AS latest_podtext2,
            ld.dates3 AS latest_dates3,
            ld.podtext3 AS latest_podtext3,
            ld.dates4 AS latest_dates4,
            ld.podtext4 AS latest_podtext4,
            ld.dates5 AS latest_dates5,
            ld.podtext5 AS latest_podtext5,
            ld.dates6 AS latest_dates6,
            ld.podtext6 AS latest_podtext6,
            ld.dates7 AS latest_dates7,
            ld.podtext7 AS latest_podtext7
        FROM pacienti p
        JOIN mesiac_pacient mp_current
            ON mp_current.pacient_id = p.id AND mp_current.mesiac_id = ?
        LEFT JOIN latest_data ld ON ld.pacient_id = p.id
        WHERE p.sestra = ?
        ORDER BY p.meno
    """, (nurse_id, month_id, nurse_id)).fetchall()

    conn.close()
    return [dict(row) for row in rows]

def update_dekurz_number(patient_id, dekurz_number):
    conn = get_db_connection()
    conn.execute("UPDATE pacienti SET cislo_dekurzu = ? WHERE id = ?", (dekurz_number, patient_id))
    conn.commit()
    conn.close()
