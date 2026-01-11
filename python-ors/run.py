from app import create_app
from easy import InstanceManager, Config, Logger

if __name__ == "__main__":
    InstanceManager({
            "main config" : Config(
                configPath="configs/main-config.json",
                logger=Logger()
            )
    }) # register main config file

    app = create_app()
    app.run(host="0.0.0.0", port=InstanceManager().getObject("main config").getValue("port"))
