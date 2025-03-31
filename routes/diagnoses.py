from flask import Blueprint, request, redirect, url_for, render_template
from models.diagnosis import Diagnosis
from utils.database import get_db_connection

diagnosis_bp = Blueprint("diagnosis", __name__)

@diagnosis_bp.route('/diagnosis/create', methods=['GET', 'POST'])
def create_diagnosis():
    if request.method == 'POST':
        data = request.form
        conn = get_db_connection()
        conn.execute("INSERT INTO diagnozy (nazov, kod) VALUES (?, ?)",
                     (data['nazov'], data['kod']))
        conn.commit()
        conn.close()
        return redirect(url_for('main.settings'))

    return render_template("create/diagnosis.html")

@diagnosis_bp.route('/diagnosis/update/<int:id>', methods=['GET', 'POST'])
def update_diagnosis(id):
    if request.method == 'POST':
        data = request.form
        conn = get_db_connection()
        conn.execute("UPDATE diagnozy SET nazov = ?, kod = ? WHERE id = ?",
                     (data['nazov'], data['kod'], id))
        conn.commit()
        conn.close()
        return redirect(url_for('main.settings'))

    diagnosis = get_diagnosis(id)
    if not diagnosis:
        return "Diagnóza nenájdená", 404

    return render_template("details/diagnosis.html", diagnosis=diagnosis)

@diagnosis_bp.route('/diagnosis/delete/<int:id>', methods=['POST'])
def delete_diagnosis(id):
    conn = get_db_connection()
    conn.execute("DELETE FROM diagnozy WHERE id = ?", (id,))
    conn.commit()
    conn.close()
    return redirect(url_for('main.settings'))

@diagnosis_bp.route('/diagnosis/search')
def search_diagnoses():
    query = request.args.get('q', '').strip()

    conn = get_db_connection()
    rows = conn.execute("""
        SELECT * FROM diagnozy
        WHERE LOWER(nazov) LIKE ? OR LOWER(kod) LIKE ?
    """, (f"%{query.lower()}%", f"%{query.lower()}%")).fetchall()
    conn.close()

    results = [Diagnosis(row).__dict__ for row in rows]
    return results  # Optionally: jsonify(results)

def get_diagnoses():
    conn = get_db_connection()
    rows = conn.execute("SELECT * FROM diagnozy").fetchall()
    conn.close()
    return [Diagnosis(row) for row in rows]

def get_diagnosis(id):
    conn = get_db_connection()
    row = conn.execute("SELECT * FROM diagnozy WHERE id = ?", (id,)).fetchone()
    conn.close()
    return Diagnosis(row) if row else None

@diagnosis_bp.route('/diagnosis/list')
def list_diagnoses():
    diagnoses = get_diagnoses()
    return render_template("details/diagnoses.html", diagnoses=diagnoses)
