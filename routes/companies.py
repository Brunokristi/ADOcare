from flask import Blueprint, request, redirect, url_for, render_template
from models.company import Company
from utils.database import get_db_connection

company_bp = Blueprint("company", __name__)

@company_bp.route('/company/create', methods=['GET', 'POST'])
def create_company():
    if request.method == 'POST':
        data = request.form
        conn = get_db_connection()
        conn.execute("""
            INSERT INTO adosky (nazov, ico, ulica, mesto, identifikator, kod)
            VALUES (?, ?, ?, ?, ?, ?)
        """, (data['nazov'], data['ico'], data['ulica'], data['mesto'], data['identifikator'], data['kod']))
        conn.commit()
        conn.close()
        return redirect(url_for('main.settings'))

    return render_template("create/company.html")

@company_bp.route('/company/update/<int:id>', methods=['GET', 'POST'])
def update_company(id):
    if request.method == 'POST':
        data = request.form
        conn = get_db_connection()
        conn.execute("""
            UPDATE adosky
            SET nazov = ?, ico = ?, ulica = ?, mesto = ?, identifikator = ?, kod = ?
            WHERE id = ?
        """, (
            data['nazov'], data['ico'], data['ulica'], data['mesto'],
            data['identifikator'], data['kod'], id
        ))
        conn.commit()
        conn.close()
        return redirect(url_for('main.settings'))

    company = get_company(id)
    if not company:
        return "Spoločnosť nenájdená", 404

    return render_template("details/company.html", company=company)

@company_bp.route('/company/delete/<int:id>', methods=['POST'])
def delete_company(id):
    conn = get_db_connection()
    conn.execute("DELETE FROM adosky WHERE id = ?", (id,))
    conn.commit()
    conn.close()
    return redirect(url_for('main.settings'))

def get_companies():
    conn = get_db_connection()
    rows = conn.execute("SELECT * FROM adosky").fetchall()
    conn.close()
    return [Company(row) for row in rows]

def get_company(id):
    conn = get_db_connection()
    row = conn.execute("SELECT * FROM adosky WHERE id = ?", (id,)).fetchone()
    conn.close()
    return Company(row) if row else None
