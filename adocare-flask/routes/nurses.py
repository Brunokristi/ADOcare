from flask import Blueprint, request, redirect, url_for, render_template, session, jsonify
from models.nurse import Nurse
from utils.database import get_db_connection
from routes.cars import get_cars
from routes.companies import get_companies

from flask_login import login_required

nurse_bp = Blueprint("nurse", __name__)

@nurse_bp.route('/nurse/create', methods=['GET', 'POST'])
@login_required
def create_nurse():
    if request.method == 'POST':
        data = request.form
        conn = get_db_connection()
        conn.execute("""
            INSERT INTO sestry (meno, kod, uvazok, vozidlo, ados, phone_number)
            VALUES (?, ?, ?, ?, ?, ?)""",
            (data['meno'], data['kod'], data['uvazok'], data['vozidlo'], data['ados'], data['phone_number'])
        )
        conn.commit()
        conn.close()
        return redirect(url_for('main.settings'))

    cars = get_cars()
    comapnies = get_companies()
    return render_template("create/nurse.html", cars=cars, comapnies=comapnies)

@nurse_bp.route('/nurse/update/<int:id>', methods=['GET', 'POST'])
@login_required
def update_nurse(id):
    if request.method == 'POST':
        data = request.form
        conn = get_db_connection()
        conn.execute("""
            UPDATE sestry
            SET meno = ?, kod = ?, uvazok = ?, vozidlo = ?, ados = ?, phone_number = ?
            WHERE id = ?
        """, (
            data['meno'], data['kod'], data['uvazok'],
            data['vozidlo'], data['ados'], data['phone_number'], id
        ))
        conn.commit()
        conn.close()

    cars = get_cars()
    companies = get_companies()
    nurse = get_nurse(id)
    if not nurse:
        return "Sestra nenájdená", 404

    return render_template("fragments/nurses.html", nurse=nurse, cars=cars, companies=companies)

@nurse_bp.route('/nurse/delete/<int:id>', methods=['POST'])
@login_required
def delete_nurse(id):
    conn = get_db_connection()
    conn.execute("DELETE FROM sestry WHERE id = ?", (id,))
    conn.commit()
    conn.close()
    return redirect(url_for('main.settings'))

def get_nurses():
    conn = get_db_connection()
    rows = conn.execute("SELECT * FROM sestry").fetchall()
    conn.close()
    return [Nurse(row) for row in rows]

def get_nurse(id):
    conn = get_db_connection()
    row = conn.execute("SELECT * FROM sestry WHERE id = ?", (id,)).fetchone()
    conn.close()
    return Nurse(row) if row else None

@nurse_bp.route('/nurse/select', methods=['POST'])
@login_required
def select_nurse():
    data = request.get_json()

    session['nurse'] = {
        'id': data.get('id'),
        'meno': data.get('meno'),
        'kod': data.get('kod'),
        'uvazok': data.get('uvazok'),
        'vozidlo': data.get('vozidlo'),
        'ados': data.get('ados'),
        'phone_number': data.get('phone_number')
    }

    return jsonify(success=True)
