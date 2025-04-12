from flask import Blueprint, request, redirect, url_for, render_template, jsonify, session
from models.patient import Patient
import json
from utils.database import get_db_connection
from routes.diagnoses import get_diagnosis
from routes.doctors import get_doctors
from routes.companies import get_companies
from routes.insurances import get_insurances
from utils.geocode import geocode_address

patient_bp = Blueprint("patient", __name__)

@patient_bp.route('/patient/create', methods=['GET', 'POST'])
def create_patient():
    if request.method == 'POST':
        data = request.form

        address = data['adresa']
        longitude, latitude = geocode_address(address)

        conn = get_db_connection()
        conn.execute("""
            INSERT INTO pacienti (
                meno, rodne_cislo, adresa, poistovna, ados,
                sestra, odosielatel, pohlavie, cislo_dekurzu, diagnoza, longitude, latitude
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""",
            (
                data['meno'], data['rodne_cislo'], data['adresa'], data['poistovna'],
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
def update_patient(id):
    if request.method == 'POST':
        data = request.form

        address = data['adresa']
        longitude, latitude = geocode_address(address)

        conn = get_db_connection()
        conn.execute("""
            UPDATE pacienti SET
                meno = ?, rodne_cislo = ?, adresa = ?, poistovna = ?, ados = ?,
                sestra = ?, odosielatel = ?, pohlavie = ?, cislo_dekurzu = ?, diagnoza = ?, longitude = ?, latitude = ?
            WHERE id = ?""",
            (
                data['meno'], data['rodne_cislo'], data['adresa'], data['poistovna'],
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
def delete_patient(id):
    conn = get_db_connection()
    conn.execute("DELETE FROM pacienti WHERE id = ?", (id,))
    conn.commit()
    conn.close()
    return redirect(url_for('patient.list_patients'))

@patient_bp.route('/patient/search')
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
    """, (nurse_id, f"%{query}%", f"%{query}%")).fetchall()
    conn.close()

    results = [Patient(row).__dict__ for row in rows]
    return jsonify(results)

@patient_bp.route('/patients/list/')
def list_patients():
    patients = get_patients()
    return render_template("details/patients.html", patients=patients)

@patient_bp.route('/patients/menu/')
def menu():
    day = session.get("month", {}).get("prvy_den")
    patients = get_patients_in_day(day)
    return render_template("dekurzy/menu.html", patients=patients)

@patient_bp.route('/patients/day/<date_str>')
def patients_in_day(date_str):
    data = get_patients_in_day(date_str)
    return jsonify(data)

@patient_bp.route('/patients/month/')
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
    rows = conn.execute("SELECT * FROM pacienti WHERE sestra = ?", (nurse_id,)).fetchall()
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
        WHERE d.mesiac = ? AND d.datum = ?
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
    if not nurse_id:
        return []

    conn = get_db_connection()
    rows = conn.execute("""
        SELECT p.*, mp.*
        FROM pacienti p
        LEFT JOIN mesiac_pacient mp 
            ON mp.pacient_id = p.id 
            AND mp.mesiac_id = (
                SELECT m.id
                FROM mesiac_pacient mp_inner
                JOIN mesiac m ON m.id = mp_inner.mesiac_id
                WHERE mp_inner.pacient_id = p.id
                ORDER BY m.rok DESC, m.mesiac DESC
                LIMIT 1
            )
        WHERE p.sestra = ?
        ORDER BY p.meno
    """, (nurse_id,)).fetchall()
    conn.close()
    return rows

def update_dekurz_number(patient_id, dekurz_number):
    conn = get_db_connection()
    conn.execute("UPDATE pacienti SET cislo_dekurzu = ? WHERE id = ?", (dekurz_number, patient_id))
    conn.commit()
    conn.close()