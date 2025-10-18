import json
from pathlib import Path
from easy import Logger, Config

class AppConfig:
    def __init__(self, raw: dict, cfg_obj: Config, logger: Logger):
        self._raw = raw 
        self._cfg = cfg_obj
        self._logger = logger

    def getValue(self, *keys):
        if len(keys) == 1:
            return self._cfg.getValue(keys[0])
        d = self._raw
        for k in keys:
            d = d[k]
        return d

def load_config_dict(config_path: str) -> AppConfig:
    path = Path(config_path).resolve()
    if not path.exists():
        raise RuntimeError(f"Config file not found: {path}")

    with path.open("r", encoding="utf-8") as f:
        raw = json.load(f)

    cfg_obj = Config(configPath=str(path), logger=Logger(2))
    return AppConfig(raw=raw, cfg_obj=cfg_obj, logger=Logger(2))
