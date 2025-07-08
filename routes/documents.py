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
    oldFormData = conn.execute("SELECT * FROM documents_navrh", ()).fetchall()
    adoskaData = conn.execute("""
        SELECT nazov, ulica, mesto FROM adosky
        WHERE identifikator = ?
    """, (current_user.username,)).fetchall()
    conn.close()

    results = [dict(row) for row in oldFormData][0]
    results = results | [dict(row) for row in adoskaData][0]

    return jsonify(results)

@documents_bp.route('/documents/getAdditionDataByRodneCislo', methods=['GET'])
@login_required
def getAdditionDataByRodneCislo():
    conn = get_db_connection()
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
    """, (request.args.get('rodne_cislo', ''),)).fetchall()
    conn.close()

    results = [dict(row) for row in rows][0]
    results['poitovnaFirstCode'] = results.pop('kod')
    results['doctorName'] = results.pop('meno')
    return jsonify(results)
