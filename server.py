from flask import Flask, request, jsonify, send_file, render_template, redirect, url_for, flash
from flask_cors import CORS
import random
import re
import os
import csv
from reportlab.lib.pagesizes import A4
from reportlab.pdfgen import canvas
from reportlab.lib import colors
import platform
import subprocess
import webbrowser
import threading
import sys
import sqlite3
from datetime import datetime, timedelta
import calendar
from reportlab.lib.utils import simpleSplit
import json
import signal
import unicodedata
from flask import session


app = Flask(__name__)
CORS(app)

class Nurse:
    def __init__(self, row):
        self.id = row['id']
        self.meno = row['meno']

DATABASE_FILE = "ados_database.db"
app.secret_key = "a3f8d3e87b5a4e5f9c6d4b2f6a1e8c3d"

def get_nurses():
    conn = get_db_connection()
    rows = conn.execute("SELECT * FROM sestry").fetchall()
    conn.close()
    return [Nurse(row) for row in rows]


@app.route("/")
def index():
    nurses = get_nurses()
    return render_template("login.html", nurses=nurses)




def replace_slovak_chars(text):
    replacements = {
        "č": "c", "Č": "C", "ť": "t", "Ť": "T", "ž": "z", "Ž": "Z",
        "ý": "y", "Ý": "Y", "ú": "u", "Ú": "U", "ľ": "l", "Ľ": "L",
        "ď": "d", "Ď": "D", "ň": "n", "Ň": "N", "ó": "o", "Ó": "O",
        "ř": "r", "Ř": "R", "ě": "e", "Ě": "E"
    }
    if isinstance(text, bytes):
        text = text.decode('utf-8')
    return "".join(replacements.get(char, char) for char in text)

def split_by_chars(text, char_limit):
    return [text[i:i + char_limit] for i in range(0, len(text), char_limit)]


def normalize_str(s):
    return ''.join(
        c for c in unicodedata.normalize('NFD', s)
        if unicodedata.category(c) != 'Mn'
    ).lower()

def generate_pdf(editable_schedule, meno, rodne_cislo, adresa, poistovna, name_worker, company, entry_number):
    if os.name == "nt":
        documents_path = os.path.join(os.environ["USERPROFILE"], "Desktop", "ADOS")
    else:
        documents_path = os.path.expanduser("~/Desktop/ADOS")

    os.makedirs(documents_path, exist_ok=True)

    sanitized_rc = re.sub(r'[^0-9]', '', rodne_cislo)
    document_date = str(editable_schedule[0][0])
    sanitized_name = meno.replace(" ", "_")

    pdf_filename = f"{sanitized_rc}_{document_date}_{sanitized_name}.pdf"
    pdf_path = os.path.join(documents_path, pdf_filename)
    c = canvas.Canvas(pdf_path, pagesize=A4)
    width, height = A4
    page_number = int(entry_number)

    def draw_header():
        c.setFont("Helvetica-Bold", 14)
        title_text = replace_slovak_chars("DEKURZ OŠETROVATELSKEJ STAROSTLIVOSTI")
        title_width = c.stringWidth(title_text, "Helvetica-Bold", 14)
        c.drawString((width - title_width) / 2, height - 40, title_text)

        c.setFont("Helvetica", 10)
        c.drawString(width - 200, height - 60, f"Poradové císlo strany dekurzu: {page_number}")

        c.setStrokeColor(colors.black)
        c.rect(50, height - 110, width - 100, 45, stroke=1, fill=0)

        company_info = {
            "Andramed": ["Andramed, o.z.", "SNP 8, 98601 Fiľakovo", "ADOS"],
            "ADAMED": ["ADOS ADANED s. r. o.", "Jánošíkova 4989/2 979 01 Rimavská Sobota", "ADOS"]
        }
        company_details = company_info.get(company, ["", "", ""])

        c.setFont("Helvetica", 10)
        c.drawString(55, height - 75, replace_slovak_chars(company_details[0]))
        c.drawString(55, height - 90, replace_slovak_chars(company_details[1]))
        c.drawString(55, height - 105, replace_slovak_chars(company_details[2]))

        c.drawString(55, height - 120, replace_slovak_chars("Meno, priezvisko, titul pacienta/pacientky:"))
        c.drawString(400, height - 120, replace_slovak_chars("Rodné číslo:"))

        c.setFont("Helvetica-Bold", 12)
        c.drawString(55, height - 140, replace_slovak_chars(meno))
        c.drawString(400, height - 140, replace_slovak_chars(rodne_cislo))
        if (poistovna == "24 – DÔVERA zdravotná poisťovňa, a. s."):
            c.drawString(500, height - 140, replace_slovak_chars("24"))
        elif (poistovna == "25 – VŠEOBECNÁ zdravotná poisťovňa, a. s."):
            c.drawString(500, height - 140, replace_slovak_chars("25"))
        elif (poistovna == "27 – UNION zdravotná poisťovňa, a. s."):
            c.drawString(500, height - 140, replace_slovak_chars("27"))

        c.rect(50, height - 150, width - 100, 40, stroke=1, fill=0)
        c.line(395, height - 150, 395, height - 110)
        c.rect(50, height - 185, width - 100, 35, stroke=1, fill=0)

        c.setFont("Helvetica", 10)
        c.drawString(55, height - 170, replace_slovak_chars("Dátum a"))
        c.drawString(55, height - 182, replace_slovak_chars("čas zápisu:"))

        c.line(145, height - 185, 145, height - 150)

        c.drawString(150, height - 170, replace_slovak_chars("Rozsah poskytnutej ZS a služieb súvisiacich s poskytnutím ZS, identifikácia ošetrujúceho"))
        c.drawString(150, height - 180, replace_slovak_chars("zdravotného pracovníka (meno, priezvisko, odtlačok pečiatky a podpis)"))

        c.rect(50, height - 800, width - 100, 615, stroke=1, fill=0)
        c.line(145, height - 800, 145, height - 185)

    draw_header()
    c.setFont("Helvetica", 10)
    y_position = height - 200
    page_margin = 50
    bottom_limit = page_margin + 100

    for date, zs_time, write_time, text in editable_schedule:
        text = zs_time + ": " + text
        lines = text.split("\n")  # ✅ respect user-inserted newlines
        wrapped_lines = []

        for line in lines:
            wrapped = simpleSplit(line, "Helvetica", 10, 390)
            wrapped_lines.extend(wrapped if wrapped else [""])

        wrapped_lines = [replace_slovak_chars(line) for line in wrapped_lines]

        # Page break if needed
        if y_position < bottom_limit:
            c.showPage()
            page_number += 1
            draw_header()
            y_position = height - 200

        c.drawString(55, y_position, date)
        c.drawString(55, y_position - 10, write_time)

        for line in wrapped_lines:
            if y_position < bottom_limit:
                c.showPage()
                page_number += 1
                draw_header()
                y_position = height - 200

            c.drawString(150, y_position, line)
            y_position -= 15

        # Signature section
        if y_position < 100:
            c.showPage()
            page_number += 1
            draw_header()
            y_position = height - 200

        c.setFont("Helvetica-Bold", 10)
        c.drawString(150, y_position, name_worker)
        c.setFont("Helvetica", 10)
        c.drawString(300, y_position, "Podpis:")
        y_position -= 20

        if y_position < 100:
            c.showPage()
            page_number += 1
            draw_header()
            y_position = height - 200

    c.save()
    print("PDF generated successfully!")
    update_patient_db(rodne_cislo, str(page_number + 1))
    open_pdf(pdf_path)
    return pdf_path
def open_pdf(pdf_path):
    if os.name == "nt":
        os.system(f'start "" "{pdf_path}"')
    else:
        os.system(f"open '{pdf_path}'") 








@app.route("/add_nurse", methods=["POST"])
def add_nurse():
    name = request.form.get("meno").strip()

    if not name:
        flash("Nurse name cannot be empty!", "warning")
        return redirect(url_for("login"))

    conn = get_db_connection()
    try:
        conn.execute("INSERT INTO sestry (meno) VALUES (?)", (name,))
        conn.commit()
        flash(f"Nurse '{name}' added successfully!", "success")
    except sqlite3.Error as e:
        flash(f"Database error: {e}", "danger")
    finally:
        conn.close()

    return redirect(url_for("index"))

@app.route("/remove_nurse", methods=["GET"])
def remove_nurse():
    nurse_id = request.args.get("id")

    if not nurse_id:
        flash("Invalid request. No nurse ID provided!", "warning")
        return redirect(url_for("login"))

    conn = get_db_connection()
    try:
        cursor = conn.execute("DELETE FROM sestry WHERE id = ?", (nurse_id,))
        conn.commit()

        if cursor.rowcount > 0:
            flash(f"Nurse removed successfully!", "success")
        else:
            flash(f"No nurse found with the given ID.", "warning")

    except sqlite3.Error as e:
        flash(f"Database error: {e}", "danger")
    finally:
        conn.close()

    return redirect(url_for("index"))







def generate_dates(cursor, month_id, year, month):
    days_in_month = calendar.monthrange(year, month)[1]

    for day in range(1, days_in_month + 1):
        date_str = f"{year}-{month:02d}-{day:02d}"
        cursor.execute("INSERT INTO dni (datum, mesiac) VALUES (?, ?)", (date_str, month_id))

@app.route("/month", methods=["POST"])
def month():
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        data = request.get_json()

        nurse_id = data.get("nurse")
        month_input = data.get("month")
        zs_start_time = data.get("zs_start_time")
        zs_end_time = data.get("zs_end_time")
        write_start_time = data.get("write_start_time")
        write_end_time = data.get("write_end_time")

        if not month_input or not nurse_id:
            return jsonify({"error": "Mesiac a sestra sú povinné!"}), 400

        year, month = map(int, month_input.split("-"))

        # Check if the month already exists
        cursor.execute("SELECT id FROM mesiac WHERE mesiac = ? AND rok = ? AND sestra_id = ?", (month, year, nurse_id))
        existing_month = cursor.fetchone()

        if existing_month:
            # Update the existing entry
            cursor.execute("""
                UPDATE mesiac 
                SET vysetrenie_start = ?, vysetrenie_koniec = ?, vypis_start = ?, vypis_koniec = ? 
                WHERE id = ?
            """, (zs_start_time, zs_end_time, write_start_time, write_end_time, existing_month["id"]))
            conn.commit()

            flash("Mesiac bol aktualizovaný!", "info")
        else:
            # Insert a new month
            cursor.execute("""
                INSERT INTO mesiac (mesiac, rok, vysetrenie_start, vysetrenie_koniec, vypis_start, vypis_koniec, sestra_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            """, (month, year, zs_start_time, zs_end_time, write_start_time, write_end_time, nurse_id))
            conn.commit()

            # Retrieve the new month ID
            cursor.execute("SELECT id FROM mesiac WHERE mesiac = ? AND rok = ? AND sestra_id = ?", (month, year, nurse_id))
            month_id = cursor.fetchone()["id"]

            generate_dates(cursor, month_id, year, month)
            conn.commit()

            flash("Mesiac a jeho dátumy boli úspešne vytvorené!", "success")

        conn.close()

        # Redirect to the detail page for that month
        return jsonify({"redirect": url_for("detail", nurse_id=nurse_id, year=year, month=month)})

    except Exception as e:
        print("Error:", e)
        return jsonify({"error": str(e)}), 500







@app.route("/generate_schedule", methods=["POST"])
def generate_schedule():
    try:
        if not request.is_json:
            return jsonify({"error": "Invalid request format, expected JSON"}), 400

        data = request.get_json()
        patient_id = data.get("patient_id")
        nalez = data.get("nalez", "")
        dekurz_text_0 = data.get("podtext_1", "")
        dates_list_0 = data.get("dates_list_1", []) if isinstance(data.get("dates_list_1"), list) else []

        print("text dekurzu", dekurz_text_0)

        dekurz_text_1 = data.get("podtext_2", "")
        dates_list_1 = data.get("dates_list_2", []) if isinstance(data.get("dates_list_2"), list) else []

        dekurz_text_2 = data.get("podtext_3", "")
        dates_list_2 = data.get("dates_list_3", []) if isinstance(data.get("dates_list_3"), list) else []

        dekurz_text_3 = data.get("podtext_4", "")
        dates_list_3 = data.get("dates_list_4", []) if isinstance(data.get("dates_list_4"), list) else []

        dekurz_text_4 = data.get("podtext_5", "")
        dates_list_4 = data.get("dates_list_5", []) if isinstance(data.get("dates_list_4"), list) else []

        dekurz_text_5 = data.get("podtext_6", "")
        dates_list_5 = data.get("dates_list_6", []) if isinstance(data.get("dates_list_4"), list) else []

        dekurz_text_6 = data.get("podtext_7", "")
        dates_list_6 = data.get("dates_list_7", []) if isinstance(data.get("dates_list_4"), list) else []

        dekurz_text_7 = data.get("podtext_8", "")
        dates_list_7 = data.get("dates_list_8", []) if isinstance(data.get("dates_list_4"), list) else []

        entry_number = data.get("entry_number", "")

        nurse_id = data.get("nurse_id")

        start = datetime.strptime(data.get("start"), "%Y-%m-%d").date()
        end = datetime.strptime(data.get("end"), "%Y-%m-%d").date()
        end = end + timedelta(days=1)

        print("Start:", start)

        conn = get_db_connection()
        cursor = conn.cursor()

        cursor.execute("""
            SELECT meno, rodne_cislo, adresa, poistovna, ados 
            FROM pacienti 
            WHERE id = ?
        """, (patient_id,))
        patient = cursor.fetchone()

        if not patient:
            return jsonify({"error": "Patient not found"}), 404
        
        print("Patient:", patient)

        meno, rodne_cislo, adresa, poistovna, company = patient

        cursor.execute("""
            SELECT dni.datum, den_pacient.vysetrenie, den_pacient.vypis
            FROM den_pacient
            JOIN dni ON den_pacient.den_id = dni.id
            WHERE den_pacient.pacient_id = ? AND dni.datum >= ? AND dni.datum <= ?
            ORDER BY dni.datum
        """, (patient_id, start, end))
        schedule_entries = [tuple(row) for row in cursor.fetchall()]


        cursor.execute("""
            SELECT meno FROM sestry WHERE id = ?
        """, (nurse_id,))
        name_worker = cursor.fetchone()
        name_worker = name_worker[0] if name_worker else "Unknown Nurse"  # Extract value or set default


        conn.close()

        if not schedule_entries:
            return jsonify({"error": "No schedule generated"}), 400

        text_by_date = {}

        def add_text_to_date(date_list, text):
            if isinstance(date_list, list):
                for date in date_list:
                    if date.strip():
                        if date not in text_by_date:
                            text_by_date[date] = []
                        text_by_date[date].append(text)

        add_text_to_date(dates_list_0, dekurz_text_0)
        add_text_to_date(dates_list_1, dekurz_text_1)
        add_text_to_date(dates_list_2, dekurz_text_2)
        add_text_to_date(dates_list_3, dekurz_text_3)
        add_text_to_date(dates_list_4, dekurz_text_4)
        add_text_to_date(dates_list_5, dekurz_text_5)
        add_text_to_date(dates_list_6, dekurz_text_6)
        add_text_to_date(dates_list_7, dekurz_text_7)

        final_schedule = []
        for date, arrival_time, write_time in schedule_entries:
            combined_text = "\n".join(text_by_date.get(date, []))

            # Skip this day if there's no combined text at all
            if not combined_text:
                print(f"⏭️ Skipping {date} — no content for the day.")
                continue

            if nalez:
                combined_text = nalez + "\n" + combined_text

            final_schedule.append([date, arrival_time, write_time, combined_text])

        print("Final schedule:", final_schedule)
        generate_pdf(final_schedule, meno, rodne_cislo, adresa, poistovna, name_worker, company, entry_number)

        return jsonify({"success": True})
    except Exception as e:
        print("Error:", e)
        return jsonify({"error": str(e)}), 500





@app.route("/save_patient", methods=["POST"])
def save_patient():
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        data = request.get_json()

        meno = data.get("meno", "").strip()
        rodne_cislo = data.get("rodne_cislo", "").strip()
        adresa = data.get("adresa", "").strip()
        poistovna = data.get("poistovna", "").strip()
        sestra = data.get("sestra", "").strip()
        ados = data.get("ados", "").strip()

        if not meno or not rodne_cislo:
            return jsonify({"error": "Chýbajúce údaje"}), 400

        cursor.execute("SELECT id FROM pacienti WHERE rodne_cislo = ?", (rodne_cislo,))
        existing_patient = cursor.fetchone()

        if existing_patient:
            patient_id = existing_patient["id"]
            cursor.execute("""
                UPDATE pacienti
                SET meno = ?, adresa = ?, poistovna = ?, sestra = ?, ados = ?
                WHERE id = ?
            """, (meno, adresa, poistovna, sestra, ados, patient_id))
        else:
            cursor.execute("""
                INSERT INTO pacienti (meno, rodne_cislo, adresa, poistovna, sestra, ados)
                VALUES (?, ?, ?, ?, ?, ?)
            """, (meno, rodne_cislo, adresa, poistovna, sestra, ados))
            patient_id = cursor.lastrowid

        conn.commit()
        conn.close()

        return jsonify({"success": True, "patient_id": patient_id})

    except Exception as e:
        print("Error:", e)
        return jsonify({"error": str(e)}), 500

@app.route("/search_patient", methods=["GET"])
def search_patient():    
    query = request.args.get("query", "").strip()
    
    if len(query) < 3:
        return jsonify([])

    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        cursor.execute("""
            SELECT id, meno, rodne_cislo, adresa, poistovna, sestra, ados, nalez, osetrenie, vedlajsie_osetrenie, koniec_mesiaca, cislo_dekurzu
            FROM pacienti
            WHERE LOWER(meno) LIKE ? OR LOWER(rodne_cislo) LIKE ?
        """, (f"%{query.lower()}%", f"%{query.lower()}%"))


        results = []
        for row in cursor.fetchall():
            results.append({
                "id": row["id"],
                "rc": row["rodne_cislo"],  # Rodné číslo
                "name": row["meno"],  # Meno
                "address": row["adresa"],  # Adresa
                "insurance": row["poistovna"],  # Poistovňa
                "worker": row["sestra"],  # ID sestry (can be used to fetch name)
                "company": row["ados"],  # ADOS
                "koniec_mesiaca": row["koniec_mesiaca"],  # Text koniec mesiaca
                "entry_number": row["cislo_dekurzu"]  # Číslo dekurzu
            })

        conn.close()
        return jsonify(results)

    except Exception as e:
        print("Error:", e)
        return jsonify({"error": str(e)}), 500

def update_patient_db(rodne_cislo, dekurz):
    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("""
        UPDATE pacienti 
        SET cislo_dekurzu = ? 
        WHERE rodne_cislo = ?
    """, (dekurz, rodne_cislo))

    conn.commit()
    conn.close()

@app.route("/update_patient", methods=["POST"])
def update_patient():
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        data = request.get_json()
        patient_id = data.get("patient_id")

        if not patient_id:
            return jsonify({"error": "Missing patient ID"}), 400

        # Update patient details
        cursor.execute("""
            UPDATE pacienti 
            SET dekurz_text_0 = ?, dekurz_text_1 = ?, dekurz_text_2 = ?, dekurz_text_3 = ?, dekurz_text_4 = ?, dekurz_text_5 = ?, dekurz_text_6 = ?, dekurz_text_7 = ?, cislo_dekurzu = ?
            WHERE id = ?
        """, (
            data.get("podtext_1", ""),
            data.get("podtext_2", ""),
            data.get("podtext_3", ""),
            data.get("podtext_4", ""),
            data.get("podtext_5", ""),
            data.get("podtext_6", ""),
            data.get("podtext_7", ""),
            data.get("podtext_8", ""),
            data.get("entry_number", ""),
            patient_id
        ))

        conn.commit()
        conn.close()

        return jsonify({"success": True})

    except Exception as e:
        print("Error:", e)
        return jsonify({"error": str(e)}), 500

@app.route("/delete_patient", methods=["POST"])
def delete_patient():
    try:
        data = request.get_json()

        if data is None:
            return jsonify({"error": "Invalid JSON received"}), 400

        patient_id = data.get("id", "")
        if not patient_id:
            return jsonify({"error": "Missing patient ID"}), 400

        # Database deletion
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("DELETE FROM pacienti WHERE id = ?", (patient_id,))
        conn.commit()
        conn.close()

        return jsonify({"success": True})

    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route("/delete_patient_day", methods=["POST"])
def delete_patient_day():
    try:
        data = request.get_json()

        if data is None:
            return jsonify({"error": "Invalid JSON received"}), 400

        patient_id = data.get("patient_id", "")
        date = data.get("date", "")

        if not patient_id or not date:
            return jsonify({"error": "Missing required parameters"}), 400

        conn = get_db_connection()
        cursor = conn.cursor()

        cursor.execute("SELECT id FROM dni WHERE datum = ?", (date,))
        result = cursor.fetchone()

        if not result:
            return jsonify({"error": "No matching date found in `dni` table"}), 404

        day_id = result[0]

        cursor.execute(
            "DELETE FROM `den_pacient` WHERE pacient_id = ? AND den_id = ?",
            (patient_id, day_id),
        )

        conn.commit()
        conn.close()

        return jsonify({"success": True})

    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route("/delete_patient_month", methods=["POST"])
def delete_patient_month():
    try:
        data = request.get_json()

        if data is None:
            return jsonify({"error": "Invalid JSON received"}), 400

        patient_id = data.get("patient_id", "")
        date = data.get("date", "")

        if not patient_id or not date:
            return jsonify({"error": "Missing required parameters"}), 400

        conn = get_db_connection()
        cursor = conn.cursor()

        # Step 1: Extract year and month from the date
        year, month, _ = date.split("-")  # YYYY-MM-DD -> Extract YYYY and MM

        # Step 2: Find all `den_id` values for this month in the `dni` table
        cursor.execute("SELECT id FROM dni WHERE strftime('%Y-%m', datum) = ?", (f"{year}-{month}",))
        results = cursor.fetchall()

        if not results:
            return jsonify({"error": "No matching days found in `dni` table"}), 404

        day_ids = [row[0] for row in results]  # Extract day IDs

        # Step 3: Delete patient records for the whole month
        cursor.execute(
            f"DELETE FROM `den_pacient` WHERE pacient_id = ? AND den_id IN ({','.join(['?'] * len(day_ids))})",
            [patient_id] + day_ids,
        )

        conn.commit()
        conn.close()

        return jsonify({"success": True})

    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route("/insert_schedule", methods=["POST"])
def insert_schedule():
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        data = request.get_json()
        patient_id = data.get("patient_id")
        year = data.get("year")
        month = data.get("month")
        schedule_dates = data.get("schedule")
        nurse_id = data.get("sestra")

        if not patient_id or not year or not month or not schedule_dates:
            return jsonify({"error": "Missing required data"}), 400

        # Get the month ID for this nurse and month
        cursor.execute("""
            SELECT id FROM mesiac 
            WHERE mesiac = ? AND rok = ? AND sestra_id = ?
        """, (month, year, nurse_id))
        mesiac_row = cursor.fetchone()

        if not mesiac_row:
            return jsonify({"error": "Mesiac not found"}), 404

        mesiac_id = mesiac_row["id"]

        # Get all dni (days) for this mesiac
        cursor.execute("""
            SELECT id, strftime('%Y-%m-%d', datum) AS datum 
            FROM dni 
            WHERE mesiac = ?
        """, (mesiac_id,))
        dni_records = {row["datum"]: row["id"] for row in cursor.fetchall()}

        # Delete any existing schedule for this patient in this month
        cursor.execute("""
            DELETE FROM den_pacient 
            WHERE pacient_id = ? 
              AND den_id IN (SELECT id FROM dni WHERE mesiac = ?)
        """, (patient_id, mesiac_id))

        # Insert new schedule
        inserted_days = []
        for schedule_date in schedule_dates:
            den_id = dni_records.get(schedule_date)
            if den_id:
                cursor.execute("""
                    INSERT INTO den_pacient (den_id, pacient_id)
                    VALUES (?, ?)
                """, (den_id, patient_id))
                inserted_days.append(den_id)

        conn.commit()
        conn.close()

        if inserted_days:
            first_day_id = min(inserted_days)
            return jsonify({
                "redirect": url_for("detail", nurse_id=nurse_id, year=year, month=month, day=first_day_id)
            })
        else:
            return jsonify({"error": "No valid schedule dates found"}), 400

    except Exception as e:
        print("Error:", e)
        return jsonify({"error": str(e)}), 500

@app.route("/update_schedule", methods=["POST"])
def update_schedule():
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        data = request.get_json()
        schedule_data = data.get("schedule")
        sestra_id = data.get("sestra_id")

        if not schedule_data:
            return jsonify({"error": "No schedule data received"}), 400

        for entry in schedule_data:
            patient_id = entry.get("patient_id")
            date = entry.get("date")
            arrival_time = entry.get("arrival_time")
            write_time = entry.get("write_time")

            cursor.execute("""
                SELECT id FROM dni 
                WHERE datum = ? AND mesiac IN 
                    (SELECT id FROM mesiac WHERE sestra_id = ?)
            """, (date, sestra_id))

            day_row = cursor.fetchone()
            if not day_row:
                print(f"No matching day found for {date}")
                continue

            day_id = day_row["id"]

            cursor.execute("""
                SELECT id FROM den_pacient WHERE den_id = ? AND pacient_id = ?
            """, (day_id, patient_id))
            existing_entry = cursor.fetchone()

            if existing_entry:
                cursor.execute("""
                    UPDATE den_pacient 
                    SET vysetrenie = ?, vypis = ? 
                    WHERE den_id = ? AND pacient_id = ?
                """, (arrival_time, write_time, day_id, patient_id))
            else:
                cursor.execute("""
                    INSERT INTO den_pacient (den_id, pacient_id, arrival_time, write_time)
                    VALUES (?, ?, ?, ?)
                """, (day_id, patient_id, arrival_time, write_time))

        conn.commit()
        conn.close()

        return jsonify({"success": True})

    except Exception as e:
        print("Error:", e)
        return jsonify({"error": str(e)}), 500



def remove_newlines(data):
    if isinstance(data, str):
        return data.replace("\r\n", "[NEWLINE]").replace("\n", "[NEWLINE]").replace("\r", "[NEWLINE]")
    elif isinstance(data, list):
        return [remove_newlines(item) for item in data]
    elif isinstance(data, dict):
        return {key: remove_newlines(value) for key, value in data.items()}
    return data


@app.route("/detail/<int:nurse_id>/", defaults={"year": None, "month": None, "day": None}, methods=["GET", "POST"])
@app.route("/detail/<int:nurse_id>/<int:year>/", defaults={"month": None, "day": None}, methods=["GET", "POST"])
@app.route("/detail/<int:nurse_id>/<int:year>/<int:month>/", defaults={"day": None}, methods=["GET", "POST"])
@app.route("/detail/<int:nurse_id>/<int:year>/<int:month>/<int:day>/", methods=["GET", "POST"])
def detail(nurse_id, year, month, day):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        # Fetch nurse details
        cursor.execute("SELECT * FROM sestry WHERE id = ?", (nurse_id,))
        nurse = cursor.fetchone()

        cursor.execute("SELECT * FROM mesiac WHERE sestra_id = ? ORDER BY rok, mesiac", (nurse_id,))
        vypisane_mesiace = cursor.fetchall()


        if not nurse:
            conn.close()
            flash("Nurse not found!", "danger")
            return redirect(url_for("index"))

        month_data = None
        days = []
        patients_in_month = []
        patients_by_day = {}

        if year and month:
            # Fetch month data
            cursor.execute("""
                SELECT * FROM mesiac WHERE mesiac = ? AND rok = ? AND sestra_id = ?
            """, (month, year, nurse_id))
            month_data = cursor.fetchone()

            if month_data:
                cursor.execute("SELECT id, datum FROM dni WHERE mesiac = ?", (month_data["id"],))
                days = [dict(row) for row in cursor.fetchall()]

                cursor.execute("""
                    SELECT DISTINCT pacienti.id AS patient_id, pacienti.*, 
                        GROUP_CONCAT(strftime('%Y-%m-%d', dni.datum)) AS scheduled_dates
                    FROM pacienti
                    JOIN den_pacient ON pacienti.id = den_pacient.pacient_id
                    JOIN dni ON den_pacient.den_id = dni.id
                    WHERE dni.mesiac = ?
                    GROUP BY pacienti.id
                """, (month_data["id"],))

                patients_in_month = [dict(row) for row in cursor.fetchall()]

                patients_in_month.sort(key=lambda p: normalize_str(p["meno"]))

                

                cursor.execute("""
                SELECT dni.datum AS day_date, pacienti.id AS patient_id, pacienti.meno AS patient_name, 
                    pacienti.adresa AS patient_adresa, pacienti.rodne_cislo, *
                FROM pacienti
                JOIN den_pacient ON pacienti.id = den_pacient.pacient_id
                JOIN dni ON den_pacient.den_id = dni.id
                WHERE dni.mesiac = ?
                ORDER BY dni.datum
            """, (month_data["id"],))

            rows = cursor.fetchall()

            # Step 1: Collect all visit dates per patient
            patient_dates = {}
            for row in rows:
                pid = row["patient_id"]
                date = row["day_date"]
                if pid not in patient_dates:
                    patient_dates[pid] = []
                patient_dates[pid].append(date)

            # Step 2: Compute min/max per patient
            date_ranges = {
                pid: {
                    "min_date": min(dates),
                    "max_date": max(dates)
                }
                for pid, dates in patient_dates.items()
            }

            # Step 3: Build patients_by_day using the final min/max
            patients_by_day = {}
            for row in rows:
                day_date = row["day_date"]
                patient_id = row["patient_id"]

                patient_data = {
                    "patient_id": patient_id,
                    "patient_rc": row["rodne_cislo"],
                    "patient_name": row["patient_name"],
                    "patient_address": row["patient_adresa"],
                    "insurance": row["poistovna"],
                    "company": row["ados"],
                    "min_date": date_ranges[patient_id]["min_date"],
                    "max_date": date_ranges[patient_id]["max_date"]
                }

                if day_date not in patients_by_day:
                    patients_by_day[day_date] = []
                patients_by_day[day_date].append(patient_data)


        conn.close()

        return render_template("index.html",
            nurse=nurse,
            vypisane_mesiace=vypisane_mesiace,
            month=month_data,
            days=days,
            patients_in_month=remove_newlines(patients_in_month),  
            patients_by_day=patients_by_day
        )

    except Exception as e:
        flash(f"Chyba: {e}", "danger")
        return redirect(url_for("index"))



def open_browser():
    webbrowser.open_new("http://127.0.0.1:5000/")

def get_db_connection():
    conn = sqlite3.connect(DATABASE_FILE)
    conn.row_factory = sqlite3.Row
    return conn




def shutdown_server():
    """Shuts down the Flask server gracefully."""
    func = request.environ.get('werkzeug.server.shutdown')

    if func:
        print("✅ Gracefully shutting down Flask via werkzeug...")
        func()
    else:
        print("⚠️ Server shutdown function not available, using alternative method...")
        threading.Thread(target=terminate_process).start()

def terminate_process():
    """Terminates the Flask process safely."""
    try:
        print("🛑 Terminating Flask process...")
        os.kill(os.getpid(), signal.SIGTERM)  # Try SIGTERM first
    except Exception as e:
        print(f"❌ Failed to terminate process: {e}")
        sys.exit(0)  # Force exit if needed

@app.route('/shutdown', methods=['POST'])
def shutdown():
    shutdown_server()
    return jsonify(success=True, message="Flask server is shutting down gracefully")



if __name__ == "__main__":
    check_db()
    threading.Timer(1.5, open_browser).start()
    app.run(host="127.0.0.1", port=5000, debug=True)