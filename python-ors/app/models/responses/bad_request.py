from typing import Dict


class Bad_request:
    def to_dict() -> Dict[str,str]:
        return {
            "error": "bad request"
        }
