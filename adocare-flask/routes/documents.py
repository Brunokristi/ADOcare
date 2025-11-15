from flask import Blueprint, render_template, jsonify, session, request
from utils.database import get_db_connection
from flask_login import current_user
import json
from flask_login import login_required

documents_bp = Blueprint("documents", __name__)

@documents_bp.route('/documents/navrh', methods=['GET', 'POST'])
@login_required
def showNavrh():
    return render_template("documents/navrh.html")

@documents_bp.route('/documents/zaznam', methods=['GET', 'POST'])
@login_required
def showZaznam():
    return render_template("documents/zaznam.html")

@documents_bp.route('/documents/dohoda', methods=['GET', 'POST'])
@login_required
def showDohoda():
    return render_template("documents/dohoda.html")

@documents_bp.route('/documents/getDohodaFormData', methods=['GET'])
@login_required
def getDohodaFormData():
    conn = get_db_connection()
    adoskaData = conn.execute("""
        SELECT nazov, ulica, mesto FROM adosky
        WHERE identifikator = ?
    """, (current_user.username,)).fetchall()
    conn.close()

    results = [dict(row) for row in adoskaData][0]

    return jsonify(results)

import sqlite3
from flask import request, jsonify

@documents_bp.route('/documents/getAdditionDataByRodneCisloForNavrh', methods=['GET'])
@login_required
def getAdditionDataByRodneCisloForNavrh():
    rodneCislo = (request.args.get('rodne_cislo') or '').replace('/', '').strip()
    if not rodneCislo:
        return jsonify({"error": "Chýba rodné číslo."}), 400

    conn = get_db_connection()
    try:
        conn.row_factory = sqlite3.Row

        # base patient + joins (LEFT JOIN so missing links don’t drop the row)
        base_row = conn.execute("""
            SELECT
                dok.meno,         -- doctor name
                poist.kod         -- insurance code
            FROM pacienti AS pac
            LEFT JOIN poistovne AS poist ON pac.poistovna  = poist.id
            LEFT JOIN doktori   AS dok   ON pac.odosielatel = dok.id
            WHERE pac.rodne_cislo = ?
            LIMIT 1
        """, (rodneCislo,)).fetchone()

        if not base_row:
            return jsonify({"error": "Pacient s daným rodným číslom nebol nájdený."}), 404

        results = dict(base_row)

        # old form data (may be missing)
        old_row = conn.execute("""
            SELECT * FROM documents_navrh
            WHERE rodne_cislo = ?
            LIMIT 1
        """, (rodneCislo,)).fetchone()

        if old_row:
            # merge without crashing
            results.update(dict(old_row))

        # rename keys safely (don’t crash if missing)
        kod = results.pop('kod', None)
        if kod is not None:
            results['poistovnaFirstCode'] = kod

        meno = results.pop('meno', None)
        if meno is not None:
            results['doctorName'] = meno

        return jsonify(results)
    finally:
        conn.close()

@documents_bp.route('/documents/storeDataFromNavrhForm', methods=['POST'])
@login_required
def storeDataFromNavrhForm():
    data = request.form

    conn = get_db_connection()
    try:
        conn.execute("""
            INSERT INTO documents_navrh (rodne_cislo, bydliskoPrechodne, epikriza, lekarskaDiagnoze, sesterskaDiagnoza, PlanOsStarostlivosty, Vykony, HCheckBox, ICheckBox, FCheckBox, PredpokladnaDlzkaStarostlivosty)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(rodne_cislo) DO UPDATE SET
                bydliskoPrechodne = excluded.bydliskoPrechodne,
                epikriza = excluded.epikriza,
                lekarskaDiagnoze = excluded.lekarskaDiagnoze,
                sesterskaDiagnoza = excluded.sesterskaDiagnoza,
                PlanOsStarostlivosty = excluded.PlanOsStarostlivosty,
                Vykony = excluded.Vykony,
                HCheckBox = excluded.HCheckBox,
                ICheckBox = excluded.ICheckBox,
                FCheckBox = excluded.FCheckBox,
                PredpokladnaDlzkaStarostlivosty = excluded.PredpokladnaDlzkaStarostlivosty;
        """, (
            data["rodne_cislo"],
            data["bydliskoPrechodne"],
            data["epikriza"],
            data["lekarskaDiagnoze"],
            data["sesterskaDiagnoza"],
            data["PlanOsStarostlivosty"],
            data["Vykony"],
            data["HCheckBox"],
            data["ICheckBox"],
            data["FCheckBox"],
            data["PredpokladnaDlzkaStarostlivosty"]
        ))
        conn.commit()

    except Exception as e:
        print(e)
        return jsonify({'message': 'Failed to save data!'}), 400

    finally:
        conn.close()

    return jsonify({'message': 'The data has been successfully retrieved!'}), 200

import sqlite3
from flask import request, jsonify

@documents_bp.route('/documents/getAdditionDataByRodneCisloForDohoda', methods=['GET'])
@login_required
def getAdditionDataByRodneCisloForDohoda():
    rodneCislo = request.args.get('rodne_cislo', '')
    conn = get_db_connection()
    oldFormData = conn.execute("SELECT * FROM documents_dohoda WHERE rodne_cislo = ?", (rodneCislo,)).fetchall()
    rows = conn.execute("""
        SELECT
            poist.kod
        FROM
            pacienti pac
        JOIN
            poistovne poist ON pac.poistovna = poist.id
        WHERE
            pac.rodne_cislo = ?
    """, (rodneCislo,)).fetchall()
    conn.close()
    results = [dict(row) for row in rows][0]
    results['poistovnaFirstCode'] = results.pop('kod')
    if oldFormData:
        results = results | [dict(row) for row in oldFormData][0]
    return jsonify(results)

@documents_bp.route('/documents/storeDataFromDohodaForm', methods=['POST'])
@login_required
def storeDataFromDohodaForm():
    data = request.form

    conn = get_db_connection()
    try:
        conn.execute("""
            INSERT INTO documents_dohoda (
                miesto_prechodneho_pobytu,
                kontaktna_osoba,
                rodne_cislo
            ) VALUES (?, ?, ?)
            ON CONFLICT(rodne_cislo) DO UPDATE SET
                miesto_prechodneho_pobytu = excluded.miesto_prechodneho_pobytu,
                kontaktna_osoba = excluded.kontaktna_osoba;
        """, (
            data["miesto_prechodneho_pobytu"],
            data["kontaktna_osoba"],
            data["rodne_cislo"]
            ))
        conn.commit()

    except Exception as e:
        print(e)
        return jsonify({'message': 'Failed to save data!'}), 400

    finally:
        conn.close()

    return jsonify({'message': 'The data has been successfully retrieved!'}), 200

@documents_bp.route('/documents/storeDataFromZaznamForm', methods=['POST'])
@login_required
def storeDataFromZaznamForm():
    # normalize RC (remove slash + trim)
    rc = (request.form.get('rodne_cislo') or '').replace('/', '').strip()
    if not rc:
        return jsonify({'ok': False, 'error': 'rodne_cislo missing'}), 400

    # flatten form -> dict (keep simple values; for multi you can adjust)
    payload = {}
    for k in request.form.keys():
        payload[k] = request.form.get(k)

    # force normalized RC into the payload
    payload['rodne_cislo'] = rc
    json_str = json.dumps(payload, ensure_ascii=False)

    conn = get_db_connection()
    try:
        conn.execute("""
          INSERT INTO documents_zaznam (rodne_cislo, form_data)
          VALUES (?, ?)
          ON CONFLICT(rodne_cislo) DO UPDATE SET
            form_data = excluded.form_data
        """, (rc, json_str))
        conn.commit()
        return jsonify({'ok': True})
    except Exception as e:
        print('storeDataFromZaznamForm error:', e)
        return jsonify({'ok': False, 'error': 'save_failed'}), 400
    finally:
        conn.close()

@documents_bp.route('/documents/getDataFromZaznamForm', methods=['GET'])
@login_required
def getDataFromZaznamForm():
    rc = (request.args.get('rodne_cislo') or '').replace('/', '').strip()
    if not rc:
        return jsonify({})  # keep 200; frontend treats ok=true as success

    conn = get_db_connection()
    try:
        row = conn.execute(
            'SELECT form_data FROM documents_zaznam WHERE rodne_cislo = ?',
            (rc,)
        ).fetchone()
    finally:
        conn.close()

    if not row or not row[0]:
        return jsonify({})  # nothing yet for this patient

    try:
        return jsonify(json.loads(row[0]))
    except Exception as _:
        # corrupt / unexpected payload – fail softly
        return jsonify({})
