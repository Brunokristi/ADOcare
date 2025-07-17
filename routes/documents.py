from flask import Blueprint, render_template, jsonify, session, request
from utils.database import get_db_connection
from flask_login import current_user

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

@documents_bp.route('/documents/getAdditionDataByRodneCisloForNavrh', methods=['GET'])
@login_required
def getAdditionDataByRodneCisloForNavrh():
    rodneCislo = request.args.get('rodne_cislo', '')
    conn = get_db_connection()
    oldFormData = conn.execute("SELECT * FROM documents_navrh WHERE rodne_cislo = ?", (rodneCislo,)).fetchall()
    rows = conn.execute("""
        SELECT
            dok.meno,
            poist.kod
        FROM
            pacienti pac
        JOIN
            poistovne poist ON pac.poistovna = poist.id
        JOIN
            doktori dok ON pac.odosielatel = dok.id
        WHERE
            pac.rodne_cislo = ?
    """, (rodneCislo,)).fetchall()
    conn.close()

    results = [dict(row) for row in rows][0]
    if (oldFormData):
        results = results | [dict(row) for row in oldFormData][0]
    results['poitovnaFirstCode'] = results.pop('kod')
    results['doctorName'] = results.pop('meno')
    return jsonify(results)

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
    results['poitovnaFirstCode'] = results.pop('kod')
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