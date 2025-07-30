from flask import Blueprint, request, redirect, url_for, render_template, jsonify, session
import json
from utils.database import get_db_connection
from routes.patients import get_all_patients_info_in_month, update_dekurz_number
from routes.macros import get_macros
import unicodedata
import os
from reportlab.lib.pagesizes import A4
from reportlab.pdfgen import canvas
from reportlab.lib import colors
from reportlab.lib.utils import simpleSplit
from collections import defaultdict
from datetime import datetime

from flask_login import login_required

dekurz_bp = Blueprint("dekurz", __name__)

@dekurz_bp.route("/dekurz", methods=["GET"])
@login_required
def dekurz():
    patients = get_all_patients_info_in_month()
    macros = get_macros()

    return render_template("dekurzy/vypis.html", patients=patients, macros=macros)

@dekurz_bp.route("/save", methods=["POST"])
@login_required
def save():
    conn = get_db_connection()
    cursor = conn.cursor()
    data = request.get_json()

    patient_id = data.get("patient_id")
    podtexty = data.get("podtexty", [])
    datumy = data.get("datumy", [])
    dates_all = data.get("dates_all", [])
    month_id = session.get("month", {}).get("id")
    entry_number = data.get("entry_number", 1)
    try:
        cursor.execute(
            "UPDATE pacienti SET last_month = ?, cislo_dekurzu = ? WHERE id = ?",
            (month_id, entry_number, patient_id)
        )

        set_parts = []
        values = []

        for i in range(8):
            set_parts.append(f"podtext{i} = ?")
            values.append(podtexty[i] if i < len(podtexty) else "")

            set_parts.append(f"dates{i} = ?")
            values.append(json.dumps(datumy[i]) if i < len(datumy) else json.dumps([]))

        set_parts.append("dates_all = ?")
        values.append(json.dumps(dates_all))
        values.extend([month_id, patient_id])

        sql = f"""
            UPDATE mesiac_pacient
            SET {', '.join(set_parts)}
            WHERE mesiac_id = ? AND pacient_id = ?
        """
        cursor.execute(sql, values)

        conn.commit()
        generate(patient_id, month_id)
        return jsonify({"success": True}), 200

    except Exception as e:
        import traceback
        traceback.print_exc()
        if conn:
            conn.rollback()
        return jsonify({"error": str(e)}), 500

    finally:
        if conn:
            conn.close()

def generate(patient_id, month_id):
    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("SELECT meno, rodne_cislo, adresa, poistovna, cislo_dekurzu FROM pacienti WHERE id = ?", (patient_id,))
    meno, rodne_cislo, adresa, poistovna_id, entry_number = cursor.fetchone()

    meno = str(meno)
    rodne_cislo = str(rodne_cislo)
    adresa = str(adresa)
    if len(adresa) > 100:
        adresa = adresa[:100] + "..."
    entry_number = str(entry_number)

    cursor.execute("SELECT kod FROM poistovne WHERE id = ?", (poistovna_id,))
    poistovna = cursor.fetchone()
    poistovna = str(poistovna[0]) if poistovna else ""

    name_worker = session.get("nurse", {}).get("meno", "Neznámy")
    company_id = session.get("nurse", {}).get("ados")

    cursor.execute("SELECT nazov, ulica, mesto FROM adosky WHERE id = ?", (company_id,))
    company_row = cursor.fetchone()
    if not company_row:
        company = ["", "", "ADOS"]
    else:
        nazov, ulica, mesto = company_row
        company = [nazov, f"{ulica}, {mesto}", "ADOS"]

    cursor.execute("""
        SELECT d.datum, dp.vysetrenie, dp.vypis
        FROM den_pacient dp
        JOIN dni d ON d.id = dp.den_id
        WHERE dp.pacient_id = ? AND d.mesiac = ?
    """, (patient_id, month_id))
    date_to_times = {}
    for date_str, vysetrenie, vypis in cursor.fetchall():
        key = date_str if isinstance(date_str, str) else date_str.strftime("%Y-%m-%d")
        v_time = vysetrenie[:5] if isinstance(vysetrenie, str) else vysetrenie.strftime("%H:%M")
        w_time = vypis[:5] if isinstance(vypis, str) else vypis.strftime("%H:%M")
        date_to_times[key] = (v_time, w_time)

    # Get mesiac-pacient texts + dates
    cursor.execute("SELECT * FROM mesiac_pacient WHERE mesiac_id = ? AND pacient_id = ?", (month_id, patient_id))
    mp_row = cursor.fetchone()

    combined = {}
    for i in range(8):
        text = mp_row[f"podtext{i}"]
        try:
            dates = json.loads(mp_row[f"dates{i}"])
        except Exception:
            dates = []
        for d in dates:
            if d not in combined:
                combined[d] = text
            else:
                combined[d] += "\n" + text

    schedule = []
    for d, text in combined.items():
        vysetrenie, vypis = date_to_times.get(d, ("10:00", "14:00"))
        schedule.append([d, vysetrenie, vypis, text])

    schedule.sort(key=lambda x: x[0])

    generate_pdf(
        editable_schedule=schedule,
        meno=meno,
        rodne_cislo=rodne_cislo,
        adresa=adresa,
        pacient_id=patient_id,
        poistovna=poistovna,
        name_worker=name_worker,
        company=company,
        entry_number=entry_number
    )
    conn.commit()
    conn.close()

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

def generate_pdf(editable_schedule, meno, rodne_cislo, adresa, pacient_id, poistovna, name_worker, company, entry_number):
    if os.name == "nt":
        documents_path = os.path.join(os.environ["USERPROFILE"], "Desktop", "ADOS_dekurzy")
    else:
        documents_path = os.path.expanduser("~/Desktop/ADOS_dekurzy")

    os.makedirs(documents_path, exist_ok=True)

    sanitized_rc = str(rodne_cislo)
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
        c.drawString(width - 200, height - 75, "Poistovna:")


        c.setStrokeColor(colors.black)
        c.rect(50, height - 110, width - 100, 45, stroke=1, fill=0)

        company_details = company

        c.setFont("Helvetica", 10)
        c.drawString(55, height - 75, replace_slovak_chars(company_details[0]))
        c.drawString(55, height - 90, replace_slovak_chars(company_details[1]))
        c.drawString(55, height - 105, replace_slovak_chars(company_details[2]))

        c.drawString(55, height - 120, replace_slovak_chars("Meno, priezvisko, titul pacienta/pacientky:"))
        c.drawString(400, height - 120, replace_slovak_chars("Rodné číslo:"))

        text = replace_slovak_chars(adresa)
        trimmed = (text[:62] + "...") if len(text) > 65 else text
        c.drawString(55, height - 145, trimmed)


        c.setFont("Helvetica-Bold", 12)
        c.drawString(width - 150, height - 75, replace_slovak_chars(poistovna))

        c.drawString(55, height - 135, replace_slovak_chars(meno))
        c.drawString(400, height - 140, rodne_cislo)
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
        lines = text.split("\n")
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

        try:
            slovak_date = datetime.strptime(date, "%Y-%m-%d").strftime("%-d.%-m.%Y")
        except ValueError:
            slovak_date = date  # fallback in case the format is unexpected

        c.drawString(55, y_position, slovak_date)
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
    update_dekurz_number(pacient_id, str(page_number + 1))
    open_pdf(pdf_path)
    return pdf_path

def open_pdf(pdf_path):
    if os.name == "nt":
        os.system(f'start "" "{pdf_path}"')
    else:
        os.system(f"open '{pdf_path}'")

