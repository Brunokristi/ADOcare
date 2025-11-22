from flask import Blueprint, request, redirect, url_for, render_template, session
from datetime import datetime, date
import calendar
from utils.database import get_db_connection
from datetime import timedelta

from flask_login import login_required

month_bp = Blueprint("month", __name__)

@month_bp.route("/month/create", methods=["GET", "POST"])
@login_required
def create_month():
    if request.method == "POST":
        data = request.form if request.form else (request.get_json(silent=True) or {})

        try:
            mesiac = int(data.get("mesiac"))
            rok = int(data.get("rok"))
        except (TypeError, ValueError):
            return "Neplatný rok alebo mesiac.", 400

        if not (1 <= mesiac <= 12):
            return "Mesiac musí byť v rozsahu 1–12.", 400

        first_day = date(rok, mesiac, 1)
        last_day = date(rok, mesiac, calendar.monthrange(rok, mesiac)[1])

        sestra_id = session.get("nurse", {}).get("id")
        if not sestra_id:
            return "Chýba identifikácia sestry.", 400

        conn = get_db_connection()
        cur = conn.cursor()

        cur.execute("""
            SELECT id FROM mesiac
            WHERE mesiac = ? AND rok = ? AND sestra_id = ?
        """, (mesiac, rok, sestra_id))
        existing = cur.fetchone()
        if existing:
            conn.close()
            return "Tento mesiac už existuje.", 409

        cur.execute("""
            INSERT INTO mesiac (
                mesiac, rok, vysetrenie_start, vysetrenie_koniec,
                vypis_start, vypis_koniec, sestra_id, prvy_den, posledny_den
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (
            mesiac,
            rok,
            data.get("vysetrenie_start"),
            data.get("vysetrenie_koniec"),
            data.get("vypis_start"),
            data.get("vypis_koniec"),
            sestra_id,
            first_day,
            last_day,
        ))

        mesiac_id = cur.lastrowid

        current_day = first_day
        while current_day <= last_day:
            cur.execute("INSERT INTO dni (datum, mesiac) VALUES (?, ?)", (current_day, mesiac_id))
            current_day += timedelta(days=1)

        conn.commit()
        conn.close()
        return "OK", 200

    return render_template("create/month.html")


@month_bp.route("/month/update/<int:id>", methods=["GET", "POST"])
@login_required
def update_month(id):
    conn = get_db_connection()

    if request.method == "POST":
        data = request.form
        conn.execute("""
            UPDATE mesiac SET
               vysetrenie_start = ?, vysetrenie_koniec = ?,
                vypis_start = ?, vypis_koniec = ?
            WHERE id = ?
        """, (
            data["vysetrenie_start"],
            data["vysetrenie_koniec"],
            data["vypis_start"],
            data["vypis_koniec"],
            id
        ))
        conn.commit()
        conn.close()
        return redirect(url_for("main.settings"))

    month = conn.execute("SELECT * FROM mesiac WHERE id = ?", (id,)).fetchone()
    conn.close()
    if not month:
        return "Mesiac nenájdený", 404

    return render_template("details/month.html", month=month)

@month_bp.route("/month/delete/<int:id>", methods=["POST"])
@login_required
def delete_month(id):
    conn = get_db_connection()
    conn.execute("DELETE FROM mesiac WHERE id = ?", (id,))
    conn.commit()
    conn.close()
    return redirect(url_for("month.list_months"))


def get_months_by_nurse():
    nurse_id = session.get("nurse", {}).get("id")
    conn = get_db_connection()
    rows = conn.execute("""
        SELECT * FROM mesiac
        WHERE sestra_id = ?
        ORDER BY rok DESC, mesiac DESC
    """, (nurse_id,)).fetchall()
    conn.close()
    return rows

@month_bp.route('/month/select', methods=['POST'])
@login_required
def select_month():
    month_data = request.json
    print("Selected month data:", month_data)  # Debugging line
    session['month'] = month_data
    return {'success': True}
