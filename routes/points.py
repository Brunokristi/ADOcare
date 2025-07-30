from flask import Blueprint, request, redirect, url_for, render_template, jsonify, session
import json
from utils.database import get_db_connection
from routes.diagnoses import get_diagnosis
from routes.doctors import get_doctors
from flask_login import login_required

points_bp = Blueprint('points', __name__)

@points_bp.route('/points/create', methods=['GET', 'POST'])
@login_required
def create_points():
    return render_template('bodovanie/bodovanie.html')