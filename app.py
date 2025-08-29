import threading
import time
import webbrowser
import sys
import os
import json

from flask import Flask, session
from flask_cors import CORS
from flask_login import LoginManager

# Import your routes and utilities here
from routes.main_routes import main
from routes.macros import macro_bp
from routes.cars import car_bp
from routes.companies import company_bp
from routes.nurses import nurse_bp
from routes.doctors import doctor_bp
from routes.diagnoses import diagnosis_bp
from routes.patients import patient_bp
from routes.insurances import insurance_bp
from routes.months import month_bp, get_months_by_nurse
from routes.schedules import schedule_bp
from routes.dekurzy import dekurz_bp
from routes.transport import transport_bp
from routes.points import points_bp
from routes.vykony import vykon_bp
from routes.auth import auth_bp, setup_login_manager
from routes.documents import documents_bp

from utils.database import DATABASE_FILE, check_db
from easy import Config, Logger
from utils.roads_manager import Road_manager


def resource_path(relative_path):
    """ Get absolute path to resource, works for dev and PyInstaller """
    try:
        base_path = sys._MEIPASS  # PyInstaller temp folder
    except Exception:
        base_path = os.path.abspath(".")
    return os.path.join(base_path, relative_path)


# Load config JSON file using absolute path (works in dev and PyInstaller)
config_path = resource_path("Configs/config.json")

with open(config_path, 'r') as f:
    config_data = json.load(f)

# Create your Config object with the correct path (pass absolute path)
config = Config(configPath=config_path, logger=Logger(2))
Road_manager(config=config, logger=Logger(2))

app = Flask(__name__)
app.secret_key = config.getValue("app secret key")
CORS(app)

login_manager = LoginManager()
login_manager.init_app(app)
login_manager.login_view = "auth.login"
setup_login_manager(login_manager)


@app.context_processor
def inject_months_dekurz():
    nurse = session.get('nurse')
    months_dekurz = get_months_by_nurse()
    if not nurse:
        return dict(months_dekurz=[])
    return dict(months_dekurz=months_dekurz)


# Register blueprints
app.register_blueprint(main)
app.register_blueprint(macro_bp)
app.register_blueprint(car_bp)
app.register_blueprint(company_bp)
app.register_blueprint(nurse_bp)
app.register_blueprint(doctor_bp)
app.register_blueprint(diagnosis_bp)
app.register_blueprint(patient_bp)
app.register_blueprint(insurance_bp)
app.register_blueprint(month_bp)
app.register_blueprint(schedule_bp)
app.register_blueprint(dekurz_bp)
app.register_blueprint(transport_bp)
app.register_blueprint(auth_bp)
app.register_blueprint(documents_bp)
app.register_blueprint(points_bp)
app.register_blueprint(vykon_bp)

check_db()


def open_browser():
    time.sleep(1)
    url = f"http://{config.getValue('host')}:{config.getValue('port')}"
    webbrowser.open_new(url)


if __name__ == "__main__":
    threading.Thread(target=open_browser).start()
    app.run(
        host=config.getValue("host"),
        port=config.getValue("port"),
        debug=config.getValue("debug mode")
    )