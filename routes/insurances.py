from flask import Blueprint, render_template, request, redirect, url_for
from models.insurance import Insurance
from utils.database import get_db_connection

insurance_bp = Blueprint("insurance", __name__)

# Get all insurances
def get_insurances():
    conn = get_db_connection()
    rows = conn.execute("SELECT * FROM poistovne").fetchall()
    conn.close()
    return [Insurance(row) for row in rows]

# Get one insurance by ID
def get_insurance(id):
    conn = get_db_connection()
    row = conn.execute("SELECT * FROM poistovne WHERE id = ?", (id,)).fetchone()
    conn.close()
    return Insurance(row) if row else None
