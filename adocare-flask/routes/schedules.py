from flask import Blueprint, request, redirect, url_for, render_template, jsonify, session
import json
from datetime import datetime, timedelta, date
from routes.patients import get_patients_by_day
from utils.database import get_db_connection
from utils.tsp import calculate_optimal_day_route


from flask_login import login_required

schedule_bp = Blueprint("schedule", __name__)

@schedule_bp.route("/schedule/insert", methods=["POST"])
@login_required
def insert_schedule():
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        data = request.get_json()
        patient_id = data.get("patient_id")
        raw_dates = data.get("dates", [])  # Format: ['01-07-2025', '02-07-2025', ...]
        month_id = session.get("month", {}).get("id")

        if not patient_id or not raw_dates:
            return jsonify({"error": "Chýba pacient alebo dátumy"}), 400

        # Convert 'dd-mm-yyyy' strings to datetime.date objects
        try:
            schedule_dates = [datetime.strptime(d, "%d-%m-%Y").date() for d in raw_dates]
        except ValueError as ve:
            return jsonify({"error": f"Nesprávny formát dátumu: {ve}"}), 400

        # Load dni records as 'yyyy-mm-dd' to match .isoformat() from schedule_dates
        cursor.execute("""
            SELECT id, datum
            FROM dni
            WHERE mesiac = ?
        """, (month_id,))
        dni_records = {
            datetime.strptime(row["datum"], "%Y-%m-%d").date().isoformat(): row["id"]
            for row in cursor.fetchall()
        }

        # Delete old records for this patient in this month
        cursor.execute("""
            DELETE FROM den_pacient
            WHERE pacient_id = ?
              AND den_id IN (SELECT id FROM dni WHERE mesiac = ?)
        """, (patient_id, month_id))

        # Save full list of dates in mesiac_pacient
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

        # Insert into den_pacient only valid dni dates
        inserted_days = []
        for d in schedule_dates:
            den_id = dni_records.get(d.isoformat())
            if den_id:
                cursor.execute("""
                    INSERT INTO den_pacient (den_id, pacient_id)
                    VALUES (?, ?)
                """, (den_id, patient_id))
                inserted_days.append(d.isoformat())

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
@login_required
def schedule_month():
    data = request.get_json()
    start_address = data.get("start")
    patients = get_patients_by_day()
    result = calculate_optimal_day_route(patients, start_address)

    return jsonify(result)

@schedule_bp.route('/schedule/delete/<int:pacient_id>', methods=['DELETE'])
@login_required
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
