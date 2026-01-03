from flask import Flask
from easy import InstanceManager


def create_app():
    app = Flask(__name__)

    from app.routes.direct_ors_request import direct_ors_request_bp
    from app.routes.tsp_solver import tsp_solver_bp

    app.register_blueprint(direct_ors_request_bp, url_prefix="/")
    app.register_blueprint(tsp_solver_bp, url_prefix="/")

    from app.utils.tsp_solver import tsp_solver
    tsp_solver(config=InstanceManager().getObject("main config"))

    return app
