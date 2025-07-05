from flask import Blueprint, render_template, request, redirect, url_for, session, jsonify
import os, signal, sys

from routes.macros import get_macro, get_macros
from routes.cars import get_car, get_cars
from routes.companies import get_company, get_companies
from routes.nurses import get_nurse, get_nurses
from routes.doctors import get_doctor, get_doctors
from routes.months import get_months_by_nurse

main = Blueprint("main", __name__)

@main.route("/")
def index():
    session.pop('nurse', None)
    nurses = get_nurses()
    return render_template("login.html", nurses=nurses)

@main.route('/nastavenia')
def settings():
    nurses = get_nurses()
    macros = get_macros()
    cars = get_cars()
    companies = get_companies()
    doctors = get_doctors()
    return render_template('settings.html', nurses=nurses, macros=macros, cars=cars, companies=companies, doctors=doctors)

@main.route("/dashboard")
def dashboard():
    months_dekurz = get_months_by_nurse()
    if 'nurse' not in session:
        return redirect(url_for("main.index"))
    return render_template("dashboard.html", months_dekurz=months_dekurz)
