from flask import Blueprint, request, jsonify
from models.vykon import Vykon
from utils.database import get_db_connection
from flask_login import login_required

vykon_bp = Blueprint("Vykon", __name__)

@vykon_bp.route('/vykon/search')
@login_required
def search_vykony():
    query = request.args.get('q', '').strip()

    conn = get_db_connection()
    rows = conn.execute("""
        SELECT * FROM vykony
        WHERE LOWER(vykon) LIKE ?
    """, (f"%{query.lower()}%",)).fetchall()
    conn.close()

    results = [Vykon(row).__dict__ for row in rows]
    return jsonify(results)

def get_vykon(vykon):
    conn = get_db_connection()
    row = conn.execute("SELECT * FROM vykony WHERE vykon = ?", (vykon,)).fetchone()
    conn.close()
    return Vykon(row) if row else None
