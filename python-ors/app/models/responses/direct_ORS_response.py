
from dataclasses import dataclass


@dataclass
class Direct_ORS_response:
    request: str
    response: str
    ret_code: int

    def to_dict(self):
        return {
            "request" : self.request,
            "response" : self.response
        }

    def get_ret_code(self) -> int:
        return self.ret_code
