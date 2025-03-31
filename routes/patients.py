from flask import Blueprint, request, redirect, url_for, render_template, jsonify, session
from models.patient import Patient
from utils.database import get_db_connection
from routes.nurses import get_nurses
from routes.doctors import get_doctors
from routes.companies import get_companies

patient_bp = Blueprint("patient", __name__)

@patient_bp.route('/patient/create', methods=['GET', 'POST'])
def create_patient():
    if request.method == 'POST':
        data = request.form
        conn = get_db_connection()
        conn.execute("""
            INSERT INTO pacienti (
                meno, rodne_cislo, adresa, poistovna, ados,
                sestra, odosielatel, pohlavie, cislo_dekurzu, last_month
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""",
            (
                data['meno'], data['rodne_cislo'], data['adresa'], data['poistovna'],
                data['ados'], data['sestra'], data['odosielatel'],
                data['pohlavie'], data['cislo_dekurzu'], data['last_month']
            ))
        conn.commit()
        conn.close()
        return redirect(url_for('main.settings'))

    nurses = get_nurses()
    doctors = get_doctors()
    companies = get_companies()
    return render_template("create/patient.html", nurses=nurses, doctors=doctors, companies=companies)

@patient_bp.route('/patient/update/<int:id>', methods=['GET', 'POST'])
def update_patient(id):
    if request.method == 'POST':
        data = request.form
        conn = get_db_connection()
        conn.execute("""
            UPDATE pacienti SET
                meno = ?, rodne_cislo = ?, adresa = ?, poistovna = ?, ados = ?,
                sestra = ?, odosielatel = ?, pohlavie = ?, cislo_dekurzu = ?, last_month = ?
            WHERE id = ?""",
            (
                data['meno'], data['rodne_cislo'], data['adresa'], data['poistovna'],
                data['ados'], data['sestra'], data['odosielatel'],
                data['pohlavie'], data['cislo_dekurzu'], data['last_month'], id
            ))
        conn.commit()
        conn.close()
        return redirect(url_for('main.settings'))

    patient = get_patient(id)
    if not patient:
        return "Pacient nenájdený", 404

    nurses = get_nurses()
    doctors = get_doctors()
    companies = get_companies()
    return render_template("details/patient.html", patient=patient, nurses=nurses, doctors=doctors, companies=companies)

@patient_bp.route('/patient/delete/<int:id>', methods=['POST'])
def delete_patient(id):
    conn = get_db_connection()
    conn.execute("DELETE FROM pacienti WHERE id = ?", (id,))
    conn.commit()
    conn.close()
    return redirect(url_for('main.settings'))

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

@patient_bp.route('/patients/list/')
def list_patients():
    patients = get_patients()
    return render_template("details/patients.html", patients=patients)
