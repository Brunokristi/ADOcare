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
from routes.insurances import get_insurance

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

    # accept both hyphen and underscore just in case
    pacient_id  = (payload.get("pacient-id") or payload.get("pacient_id") or "").strip()
    date_iso    = (payload.get("date") or "").strip()
    diagnoza_id = (payload.get("diagnoza-id") or payload.get("diagnoza") or "").strip()
    vykon_id    = (payload.get("vykon-id") or payload.get("vykon") or "").strip()
    pocet_raw   = (payload.get("pocet") or "1").strip()
    odosielatel = (payload.get("odosielatel") or "").strip()
    odpor_iso   = (payload.get("odporucenie") or "").strip()

    # coerce numbers/dates
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

        # diagnoza code
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

        # cena podľa poistovne
        cena = None
        try:
            if vykon_id:
                v = get_vykon(vykon_id)
                raw = (
                    v.poistovna25 if poistovna == "25"
                    else v.poistovna24 if poistovna == "24"
                    else v.poistovna27
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

        print(
            "Inserting point:",
            f"datum={datum}, rodne_cislo={rodne_cislo}, meno={meno}, diag={diag}, "
            f"vykon={vykon_id}, pocet={pocet}, pzs={pzs}, zpr={zpr}, "
            f"datum_ziadanky={datum_ziadanky}, cena={cena}, poistovna={poistovna}"
        )

        # 3) 11 columns => 11 placeholders
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
    return render_template('details/bodovanie.html', points=points_list)