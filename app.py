from flask import Flask
from flask_cors import CORS
from routes.main_routes import main
from routes.macros import macro_bp
from routes.cars import car_bp
from routes.companies import company_bp
from routes.nurses import nurse_bp
from routes.doctors import doctor_bp
from routes.diagnoses import diagnosis_bp
from routes.patients import patient_bp
from routes.insurances import insurance_bp
from routes.months import month_bp
from routes.schedules import schedule_bp
from routes.dekurzy import dekurz_bp
from routes.transport import transport_bp
from routes.points import points_bp
from routes.vykony import vykon_bp
from routes.auth import auth_bp, setup_login_manager
from routes.documents import documents_bp

from flask_login import LoginManager

from utils.database import DATABASE_FILE, check_db

from easy import Config, Logger

from utils.roads_manager import Road_manager

if __name__ == "__main__":
    config = Config("Configs/config.json")
    Road_manager(config=config, logger=Logger())

    app = Flask(__name__)
    app.secret_key = config.getValue("app secret key")
    CORS(app)

    login_manager = LoginManager()
    login_manager.init_app(app)
    login_manager.login_view = "auth.login"
    setup_login_manager(login_manager)

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
    app.run(host="127.0.0.1", port=5000, debug=True)
