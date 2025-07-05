from flask import Blueprint, request, redirect, url_for, render_template
from models.doctor import Doctor
from utils.database import get_db_connection

from flask_login import login_required

doctor_bp = Blueprint("doctor", __name__)

@doctor_bp.route('/doctor/create', methods=['GET', 'POST'])
@login_required
def create_doctor():
    if request.method == 'POST':
        data = request.form
        conn = get_db_connection()
        conn.execute("INSERT INTO doktori (meno, pzs, zpr) VALUES (?, ?, ?)",
                     (data['meno'], data['pzs'], data['zpr']))
        conn.commit()
        conn.close()
        return redirect(url_for('main.settings'))

    return render_template("create/doctor.html")

@doctor_bp.route('/doctor/update/<int:id>', methods=['GET', 'POST'])
@login_required
def update_doctor(id):
    if request.method == 'POST':
        data = request.form
        conn = get_db_connection()
        conn.execute("""
            UPDATE doktori SET meno = ?, pzs = ?, zpr = ? WHERE id = ?
        """, (data['meno'], data['pzs'], data['zpr'], id))
        conn.commit()
        conn.close()
        return redirect(url_for('main.settings'))

    doctor = get_doctor(id)
    if not doctor:
        return "Doktor nenájdený", 404

    return render_template("details/doctor.html", doctor=doctor)

@doctor_bp.route('/doctor/delete/<int:id>', methods=['POST'])
@login_required
def delete_doctor(id):
    conn = get_db_connection()
    conn.execute("DELETE FROM doktori WHERE id = ?", (id,))
    conn.commit()
    conn.close()
    return redirect(url_for('main.settings'))

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
