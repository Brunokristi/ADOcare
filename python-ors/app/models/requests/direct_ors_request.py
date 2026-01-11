from dataclasses import dataclass
from typing import Dict


@dataclass
class Direct_ORS_request:
    payload: str
    endpoint: str
    type: str

    def to_dict(self) -> Dict[str, str]:
        return {
            "payload" : self.payload,
            "endpoint" : self.endpoint
        }
