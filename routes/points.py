from flask import Blueprint, request, redirect, url_for, render_template, jsonify, session
import json
from datetime import datetime
from utils.database import get_db_connection
from routes.diagnoses import get_diagnosis
from routes.doctors import get_doctors
from flask_login import login_required
from routes.doctors import get_doctors
from routes.doctors import get_doctor
from routes.vykony import get_vykon
from routes.insurances import get_insurance, get_insurances
from routes.companies import get_company
from routes.nurses import get_nurse
import os
import re

points_bp = Blueprint('points', __name__)

@points_bp.route('/points/create', methods=['GET', 'POST'])
@login_required
def create_points():
    doctors = get_doctors()
    return render_template('create/bodovanie.html', doctors=doctors)


@points_bp.route("/points/save", methods=["POST"])
@login_required
def save_points():
    payload = request.get_json(silent=True) or request.form
    print(f"Received data: {payload}")

    def parse_date(s):
        if not s:
            return None
        try:
            return datetime.strptime(s, "%Y-%m-%d").date()
        except Exception:
            return None

    pacient_id  = (payload.get("pacient-id") or payload.get("pacient_id") or "").strip()
    date_iso    = (payload.get("date") or "").strip()
    diagnoza_id = (payload.get("diagnoza-id") or payload.get("diagnoza") or "").strip()
    vykon_id    = (payload.get("vykon-id") or payload.get("vykon") or "").strip()
    pocet_raw   = (payload.get("pocet") or "1").strip()
    odosielatel = (payload.get("odosielatel") or "").strip()
    odpor_iso   = (payload.get("odporucenie") or "").strip()

    try:
        pocet = max(1, int(pocet_raw))
    except Exception:
        pocet = 1

    datum = parse_date(date_iso)
    datum_ziadanky = parse_date(odpor_iso)

    conn = get_db_connection()
    cur = conn.cursor()
    try:
        # 1) select order and unpacking must match
        cur.execute("SELECT meno, rodne_cislo, poistovna FROM pacienti WHERE id = ?", (pacient_id,))
        row = cur.fetchone()
        if not row:
            return jsonify({"ok": False, "error": "Pacient neexistuje"}), 404
        meno, rodne_cislo, poistovna = row  # <-- correct order

        diag = None
        try:
            if diagnoza_id:
                d = get_diagnosis(diagnoza_id)
                diag = getattr(d, "kod", None)
        except Exception:
            diag = None

        try:
            p = get_insurance(poistovna)
            poistovna = getattr(p, "kod", None)

        except Exception:
            poistovna = None

        cena = None
        try:
            if vykon_id:
                v = get_vykon(vykon_id)
                raw = (
                    v.cena25 if poistovna == "25"
                    else v.cena24 if poistovna == "24"
                    else v.cena27
                )
                if raw is not None:
                    raw_str = str(raw).replace(" ", "").replace(",", ".")
                    cena = float(raw_str) * pocet
        except Exception:
            cena = None

        pzs = zpr = None
        if odosielatel:
            try:
                l = get_doctor(odosielatel)
                if l:
                    pzs, zpr = l.pzs, l.zpr
            except Exception:
                pzs = zpr = None

        cur.execute(
            """
            INSERT INTO bodovanie
                (datum, rodne_cislo, meno, diagnoza, vykon, pocet, pzs, zpr, datum_ziadanky, cena, poistovna)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """,
            (datum, rodne_cislo, meno, diag, vykon_id, pocet, pzs, zpr, datum_ziadanky, cena, poistovna),
        )

        conn.commit()
        return jsonify({"ok": True})

    except Exception as e:
        conn.rollback()
        print("ERROR /points/save:", repr(e))
        return jsonify({"ok": False, "error": "Uloženie zlyhalo"}), 500
    finally:
        try:
            conn.close()
        except Exception:
            pass

        try: cur.close()
        except Exception: pass
        try: conn.close()
        except Exception: pass


@points_bp.route("/points/list", methods=["GET"])
@login_required
def list_points():
    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("SELECT * FROM bodovanie ORDER BY datum asc")
    rows = cur.fetchall()
    points_list = []
    for row in rows:
        points_list.append({
            "datum": row[0],
            "rodne_cislo": row[1],
            "meno": row[2],
            "diagnoza": row[3],
            "vykon": row[4],
            "pocet": row[5],
            "pzs": row[6],
            "zpr": row[7],
            "datum_ziadanky": row[8]
        })

    poistovne = get_insurances()
    return render_template('details/bodovanie.html', points=points_list, poistovne=poistovne)

@points_bp.route("/points/generate", methods=["POST"])
@login_required
def generate_points():
    payload = request.get_json(silent=True) or request.form    
    
    cislo_faktury = payload.get("invoice_number")
    charakter_davky = payload.get("character")
    start_date = payload.get("start_date")
    end_date = payload.get("end_date")
    insurance = payload.get("poistovna")
    
    sestra = session.get("nurse" )
    sestra_id = sestra["id"]
    ados_id = sestra["ados"]
    sestra = get_nurse(sestra_id)
    ados = get_company(ados_id)


    conn = get_db_connection()
    cur = conn.cursor()
    cur.execute(
        """
        SELECT
        datum,
        rodne_cislo,
        meno,
        diagnoza,
        vykon,
        pocet,
        pzs,
        zpr,
        datum_ziadanky,
        cena,
        poistovna
        FROM bodovanie
        WHERE poistovna = ?
        AND DATE(datum) >= DATE(?)
        AND DATE(datum) <= DATE(?)
        ORDER BY datum ASC
        """,
        (insurance, start_date, end_date)
    )
    rows = cur.fetchall()


    

    typ_davky = "753b"
    ico_odosielatela = ados["ico"]
    datum_odoslania = datetime.now().strftime('%Y%m%d')
    cislo_davky = "001"
    pocet_dokladov = len(rows)
    pocet_medii = "1"
    cislo_media = "1"
    if insurance == "25": pobocka = "2521"
    elif insurance == "27": pobocka = "2700"
    elif insurance == "24": pobocka = "2400"



    
    

    identifikator_pzs = ados["identifikator"]
    kod_pzs = ados["kod"]
    kod_zp = sestra["kod"]
    uvazok = sestra["uvazok"]
    obdobie = f"{start_date[:7].replace('-', '')}"
    typ_starostlivosti = "850"
    cislo_faktury = cislo_faktury
    mena = "EUR"


    poradie = 0

 
    
    total_cost = sum((r["cena"])  for r in rows)
    print("cena celkom:", total_cost)



    filename = f"davka.{cislo_faktury}.txt"
    desktop = os.path.join(os.path.expanduser("~"), "Desktop")
    folder = os.path.join(desktop, "ADOS_davky")
    os.makedirs(folder, exist_ok=True)
    full_path = os.path.join(folder, filename)

    try:
        with open(full_path, "w", encoding="utf-8") as file:
            file.write(
                f"{charakter_davky}|{typ_davky}|{ico_odosielatela}|{datum_odoslania}|"
                f"{cislo_davky}|{pocet_dokladov}|{pocet_medii}|{cislo_media}|"
                f"{pobocka}|\n"
            )
            file.write(
                f"{identifikator_pzs}|{kod_pzs}|{kod_zp}|{uvazok}|"
                f"{obdobie}|{cislo_faktury}|{mena}|\n"
            )

            for row in rows:
                den = row["datum"][-2:] 
                diagnoza = re.sub(r'\W', '', row["diagnoza"]) if row["diagnoza"] else ''

                file.write(
                    f"{poradie}|{den}|{row['rodne_cislo']}|{row['pacient_meno']}|{'diagnoza'}|"
                    f"{row['vykon']}|{row['pocet']}|||"
                    f"|||0.00|"
                    f"0.00||||"
                    f"O|{row['pzs']}|{row['zpr']}||"
                    f"{row['id_poistenca']}|{row['pohlavie']}|\n"
                )
                poradie += 1

        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500
    finally:
        try:
            os.remove(f"/tmp/transport_data_{file_id}.json")
        except Exception as e:
            print(f"Chyba pri mazaní dočasného súboru: {e}")

    return jsonify({"ok": True, "message": "Points generation initiated."})