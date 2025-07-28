from flask import Blueprint, request, redirect, url_for, render_template
from models.doctor import Doctor
from utils.database import get_db_connection
import sqlite3

from flask_login import login_required

doctor_bp = Blueprint("doctor", __name__)
from flask import Blueprint, request, jsonify

@doctor_bp.route('/doctor/create', methods=['POST'])
@login_required
def create_doctor():
    meno = request.form.get('meno', '').strip()
    pzs = request.form.get('pzs', '').strip()
    zpr = request.form.get('zpr', '').strip()

    conn = get_db_connection()
    cursor = conn.execute(
        "SELECT id FROM doktori WHERE pzs = ? AND zpr = ?",
        (pzs, zpr)
    )
    existing = cursor.fetchone()

    if existing:
        conn.close()
        return "Tento lekár je už pridaný", 409

    conn.execute(
        "INSERT INTO doktori (meno, pzs, zpr) VALUES (?, ?, ?)",
        (meno, pzs, zpr)
    )
    conn.commit()
    conn.close()
    return "OK", 200


@doctor_bp.route('/doctor/delete/<int:id>', methods=['POST'])
@login_required
def delete_doctor(id):
    conn = get_db_connection()
    conn.execute("DELETE FROM doktori WHERE id = ?", (id,))
    conn.commit()
    conn.close()
    return "OK", 200

@doctor_bp.route('/doctors_global/search')
def search_doctors():
    query = request.args.get('q', '').strip().lower()
    if not query:
        return jsonify([])

    conn = get_db_connection()
    conn.row_factory = sqlite3.Row
    cursor = conn.execute("""
        SELECT * FROM global_doktori
        WHERE LOWER(meno) LIKE ? OR LOWER(pzs) LIKE ? OR LOWER(zpr) LIKE ?
    """, (f"%{query}%", f"%{query}%", f"%{query}%"))
    doctors = cursor.fetchall()
    conn.close()

    # Convert rows to list of dicts
    results = [dict(row) for row in doctors]
    return jsonify(results)


@doctor_bp.route('/doctors/list')
@login_required
def list_doctors():
    doctors = get_doctors_all()
    return render_template("create/doctors.html", doctors=doctors)

def get_doctors_all():
    conn = get_db_connection()
    rows = conn.execute("SELECT * FROM global_doktori").fetchall()
    conn.close()
    return [dict(row) for row in rows]


def get_doctors():
    conn = get_db_connection()
    rows = conn.execute("SELECT * FROM doktori").fetchall()
    conn.close()
    return [Doctor(row) for row in rows]

def get_doctor(id):
    conn = get_db_connection()
    row = conn.execute("SELECT * FROM doktori WHERE id = ?", (id,)).fetchone()
    conn.close()
    return Doctor(row) if row else None
