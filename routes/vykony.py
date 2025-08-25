from flask import Blueprint, request, jsonify
from models.vykon import Vykon
from utils.database import get_db_connection
from flask_login import login_required
from flask import render_template, session

vykon_bp = Blueprint("vykon", __name__)

@vykon_bp.route('/vykony')
def list_vykony():
    db = get_db_connection()
    rows = db.execute("SELECT * FROM vykony ORDER BY code").fetchall()
    return render_template('details/vykony.html', vykony=rows)

@vykon_bp.route('/vykon/search')
@login_required
def search_vykony():
    query = request.args.get('q', '').strip()

    conn = get_db_connection()
    rows = conn.execute("""
        SELECT * FROM vykony
        WHERE code LIKE ?
    """, (f"%{query.lower()}%",)).fetchall()
    conn.close()

    results = [Vykon(row).__dict__ for row in rows]
    return jsonify(results)

def get_vykon(vykon):
    conn = get_db_connection()
    row = conn.execute("SELECT * FROM vykony WHERE code = ?", (vykon,)).fetchone()
    conn.close()
    return Vykon(row) if row else None

@vykon_bp.route('/vykony/delete/<code>', methods=['DELETE'])
@login_required
def delete_vykon(code):
    conn = get_db_connection()
    cur = conn.execute("DELETE FROM vykony WHERE code = ?", (code,))
    conn.commit()
    conn.close()
    if cur.rowcount == 0:
        return jsonify({"ok": False, "error": "Not found"}), 404
    return jsonify({"ok": True})

@vykon_bp.route('/vykony/update/<code>', methods=['POST'])
@login_required
def update_vykon(code):
    data = request.json or {}
    description  = (data.get('description') or '').strip()
    poistovna25  = data.get('poistovna25', 0)
    poistovna24  = data.get('poistovna24', 0)
    poistovna27  = data.get('poistovna27', 0)

    conn = get_db_connection()
    cur = conn.execute("""
        UPDATE vykony
        SET description = ?, poistovna25 = ?, poistovna24 = ?, poistovna27 = ?
        WHERE code = ?
    """, (description, poistovna25, poistovna24, poistovna27, code))  # ← add code
    conn.commit()

    if cur.rowcount == 0:
        conn.close()
        return jsonify({"ok": False, "error": "Not found"}), 404

    row = conn.execute("SELECT code, description, poistovna25, poistovna24, poistovna27 FROM vykony WHERE code = ?", (code,)).fetchone()
    conn.close()
    return jsonify({"ok": True, "vykon": dict(row)})
