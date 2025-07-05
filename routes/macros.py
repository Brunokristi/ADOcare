from flask import Blueprint, render_template, request, redirect, url_for
from models.macro import Macro
from utils.database import get_db_connection

from flask_login import login_required

macro_bp = Blueprint("macro", __name__)

@macro_bp.route('/macro/create', methods=['GET', 'POST'])
@login_required
def create_macro():
    if request.method == 'POST':
        data = request.form
        conn = get_db_connection()
        conn.execute(
            "INSERT INTO makra (nazov, text, skratka, farba) VALUES (?, ?, ?, ?)",
            (data['nazov'], data['text'], data['skratka'], data['farba'])
        )
        conn.commit()
        conn.close()
        return redirect(url_for('main.settings'))  # OK to redirect elsewhere
    return render_template("create/macro.html")

@macro_bp.route('/macro/delete/<int:id>', methods=['POST'])
@login_required
def delete_macro(id):
    conn = get_db_connection()
    conn.execute("DELETE FROM makra WHERE id = ?", (id,))
    conn.commit()
    conn.close()
    return redirect(url_for('main.settings'))

@macro_bp.route('/macro/update/<int:id>', methods=['GET', 'POST'])
@login_required
def update_macro(id):
    if request.method == 'POST':
        data = request.form
        conn = get_db_connection()
        conn.execute("""
            UPDATE makra SET nazov = ?, text = ?, skratka = ?, farba = ?
            WHERE id = ?
        """, (data['nazov'], data['text'], data['skratka'], data['farba'], id))
        conn.commit()
        conn.close()
        return redirect(url_for('main.settings'))

    macro = get_macro(id)
    if not macro:
        return "Makro nenájdené", 404
    return render_template("details/macro.html", macro=macro)

def get_macros():
    conn = get_db_connection()
    rows = conn.execute("SELECT * FROM makra").fetchall()
    conn.close()
    return [Macro(row) for row in rows]

def get_macro(id):
    conn = get_db_connection()
    row = conn.execute("SELECT * FROM makra WHERE id = ?", (id,)).fetchone()
    conn.close()
    return Macro(row) if row else None
