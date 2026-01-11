from app.models.requests.direct_ors_request import Direct_ORS_request
from app.models.responses.direct_ORS_response import Direct_ORS_response
from easy import InstanceManager, Config
from flask import Flask, request, jsonify
import requests

class Execute_direct_ors_request:
    @classmethod
    def execute_request(cls, request: Direct_ORS_request) -> Direct_ORS_response:
        conf: Config = InstanceManager().getObject("main config")

        r = getattr(
            requests,
            request.type)(
                f"{conf.getValue("base url")}{request.endpoint}", json=request.payload, timeout=10
            )

        return Direct_ORS_response(
            response=r.json(),
            ret_code=r.status_code,
            request=request.to_dict()
        )
