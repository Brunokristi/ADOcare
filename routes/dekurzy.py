from flask import Blueprint, request, redirect, url_for, render_template, jsonify, session
from utils.database import get_db_connection
from routes.patients import get_patients_in_month
from routes.macros import get_macros

dekurz_bp = Blueprint("dekurz", __name__)

@dekurz_bp.route("/dekurz", methods=["GET"])
def dekurz():
    patients = get_patients_in_month()
    macros = get_macros()

    return render_template("dekurzy/vypis.html", patients=patients, macros=macros)
