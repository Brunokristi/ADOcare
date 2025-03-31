from flask import Blueprint, request, redirect, url_for, render_template
from models.car import Car
from utils.database import get_db_connection

car_bp = Blueprint("car", __name__)

@car_bp.route('/car/create', methods=['GET', 'POST'])
def create_car():
    if request.method == 'POST':
        data = request.form
        conn = get_db_connection()
        conn.execute("INSERT INTO auta (evc) VALUES (?)", (data['evc'],))
        conn.commit()
        conn.close()
        return redirect(url_for('main.settings'))
    
    return render_template("create/car.html")  # Optional, only if you have a form page

@car_bp.route('/car/update/<int:id>', methods=['GET', 'POST'])
def update_car(id):
    if request.method == 'POST':
        data = request.form
        conn = get_db_connection()
        conn.execute("UPDATE auta SET evc = ? WHERE id = ?", (data['evc'], id))
        conn.commit()
        conn.close()
        return redirect(url_for('main.settings'))

    car = get_car(id)
    if not car:
        return "Auto nenájdené", 404
    return render_template("details/car.html", car=car)

@car_bp.route('/car/delete/<int:id>', methods=['POST'])
def delete_car(id):
    conn = get_db_connection()
    conn.execute("DELETE FROM auta WHERE id = ?", (id,))
    conn.commit()
    conn.close()
    return redirect(url_for('main.settings'))

def get_cars():
    conn = get_db_connection()
    rows = conn.execute("SELECT * FROM auta").fetchall()
    conn.close()
    return [Car(row) for row in rows]

def get_car(id):
    conn = get_db_connection()
    row = conn.execute("SELECT * FROM auta WHERE id = ?", (id,)).fetchone()
    conn.close()
    return Car(row) if row else None
