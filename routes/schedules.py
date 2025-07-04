from flask import Blueprint, request, redirect, url_for, render_template, jsonify, session
import json
from datetime import datetime, timedelta
from routes.patients import get_patients_by_day
from utils.database import get_db_connection
from utils.tsp import calculate_optimal_day_route

schedule_bp = Blueprint("schedule", __name__)

@schedule_bp.route("/schedule/insert", methods=["POST"])
def insert_schedule():
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        data = request.get_json()
        patient_id = data.get("patient_id")
        start_date = data.get("start_date")
        end_date = data.get("end_date")
        frequency = data.get("frequency")
        exceptions = data.get("exceptions", [])
        month_id = session.get("month", {}).get("id")

        schedule_dates = generate_schedule(
            start_date=start_date,
            end_date=end_date,
            frequency=frequency,
            exceptions=exceptions
        )

        cursor.execute("""
            SELECT id, strftime('%Y-%m-%d', datum) AS datum
            FROM dni
            WHERE mesiac = ?
        """, (month_id,))
        dni_records = {row["datum"]: row["id"] for row in cursor.fetchall()}

        cursor.execute("""
            DELETE FROM den_pacient
            WHERE pacient_id = ?
              AND den_id IN (SELECT id FROM dni WHERE mesiac = ?)
        """, (patient_id, month_id))


        serialized_dates = json.dumps([d.isoformat() for d in schedule_dates])
        cursor.execute("""
            SELECT 1 FROM mesiac_pacient WHERE mesiac_id = ? AND pacient_id = ?
        """, (month_id, patient_id))

        exists = cursor.fetchone()

        if exists:
            cursor.execute("""
                UPDATE mesiac_pacient
                SET dates_all = ?
                WHERE mesiac_id = ? AND pacient_id = ?
            """, (serialized_dates, month_id, patient_id))
        else:
            cursor.execute("""
                INSERT INTO mesiac_pacient (mesiac_id, pacient_id, dates_all)
                VALUES (?, ?, ?)
            """, (month_id, patient_id, serialized_dates))



        inserted_days = []
        for schedule_date in schedule_dates:
            den_id = dni_records.get(schedule_date.isoformat())
            if den_id:
                cursor.execute("""
                    INSERT INTO den_pacient (den_id, pacient_id)
                    VALUES (?, ?)
                """, (den_id, patient_id))
                inserted_days.append(schedule_date.isoformat())

        conn.commit()
        conn.close()

        return jsonify({
            "success": True,
            "inserted_days": inserted_days
        })

    except Exception as e:
        print("Error:", e)
        return jsonify({"error": str(e)}), 500

@schedule_bp.route("/schedule/month", methods=["POST"])
def schedule_month():
    data = request.get_json()
    start_address = data.get("start")
    patients = get_patients_by_day()
    result = calculate_optimal_day_route(patients, start_address)

    return jsonify(result)

def generate_schedule(start_date, end_date, frequency, exceptions):
    start = datetime.strptime(start_date, "%Y-%m-%d").date()
    end = datetime.strptime(end_date, "%Y-%m-%d").date()
    exception_dates = set(datetime.strptime(d, "%Y-%m-%d").date() for d in exceptions)
    print("Exceptions:", exception_dates)
    current = start
    schedule = []

    if frequency == "daily":
        while current <= end:
            if current not in exception_dates:
                schedule.append(current)
            current += timedelta(days=1)

    elif frequency == "weekday":
        while current <= end:
            if current.weekday() < 5 and current not in exception_dates:
                schedule.append(current)
            current += timedelta(days=1)

    elif frequency == "3x_week":
        preferred_days = [0, 2, 4]  # Mon, Wed, Fri
        while current <= end:
            if current.weekday() in preferred_days and current not in exception_dates:
                schedule.append(current)
            current += timedelta(days=1)

    return schedule


@schedule_bp.route('/schedule/delete/<int:pacient_id>', methods=['DELETE'])
def delete_patient_from_schedule(pacient_id):

    mesiac_id = session.get("month", {}).get("id")

    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("DELETE FROM mesiac_pacient WHERE pacient_id = ? AND mesiac_id = ?", (pacient_id, mesiac_id))
    conn.commit()
    affected_rows = cursor.rowcount
    conn.close()

    if affected_rows > 0:
        return jsonify(success=True)
    else:
        return jsonify(success=False), 404