from flask import Flask

def create_app():
    app = Flask(__name__)

    from app.routes.direct_ors_request import direct_ors_request_bp

    app.register_blueprint(direct_ors_request_bp, url_prefix="/")

    return app
