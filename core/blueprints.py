def register_blueprints(app):
    from routes.main_routes import main as main_bp
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

    from core.extensions import login_manager

    app.register_blueprint(main_bp)
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

    # finalize login callbacks once
    setup_login_manager(login_manager)
