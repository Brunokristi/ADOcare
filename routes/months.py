from flask import Blueprint, request, redirect, url_for, render_template, session
from datetime import datetime, date
import calendar
from utils.database import get_db_connection

month_bp = Blueprint("month", __name__)

@month_bp.route("/month/create", methods=["GET", "POST"])
def create_month():
    if request.method == "POST":
        data = request.form

        mesiac = int(data["mesiac"])
        rok = int(data["rok"])
        first_day = date(rok, mesiac, 1)
        last_day = date(rok, mesiac, calendar.monthrange(rok, mesiac)[1])

        conn = get_db_connection()
        conn.execute("""
            INSERT INTO mesiac (
                mesiac, rok, vysetrenie_start, vysetrenie_koniec,
                vypis_start, vypis_koniec, sestra_id, prvy_den, posledny_den
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (
            mesiac,
            rok,
            data["vysetrenie_start"],
            data["vysetrenie_koniec"],
            data["vypis_start"],
            data["vypis_koniec"],
            session.get("nurse", {}).get("id"),
            first_day,
            last_day,
        ))
        conn.commit()
        conn.close()

        return redirect(url_for("main.settings"))

    return render_template("create/month.html")

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


@month_bp.route("/month/delete/<int:id>", methods=["POST"])
def delete_month(id):
    conn = get_db_connection()
    conn.execute("DELETE FROM mesiac WHERE id = ?", (id,))
    conn.commit()
    conn.close()
    return redirect(url_for("month.list_months"))
