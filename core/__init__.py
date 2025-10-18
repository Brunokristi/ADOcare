from flask import Flask
from core.config import load_config_dict
from core.extensions import cors, login_manager
from core.blueprints import register_blueprints
from utils.roads_manager import Road_manager

def create_app(config_path="Configs/config.json") -> Flask:
    app = Flask(
        __name__,
        template_folder="../templates",
        static_folder="../static"
    )


    cfg = load_config_dict(config_path)  # <-- AppConfig adapter
    app.config["SECRET_KEY"] = cfg.getValue("app secret key")
    app.config["APP_HOST"]   = cfg.getValue("host")
    app.config["APP_PORT"]   = cfg.getValue("port")
    app.config["APP_DEBUG"]  = cfg.getValue("debug mode")
    app.config["APP_CFG"]    = cfg

    cors.init_app(app)
    login_manager.init_app(app)

    Road_manager(config=cfg, logger=cfg._logger)  # <-- passes AppConfig
    register_blueprints(app)

    return app
