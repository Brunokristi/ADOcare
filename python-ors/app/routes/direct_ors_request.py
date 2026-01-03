from flask import Flask, Response, request, jsonify, Blueprint
from app.models.requests.direct_ors_request import Direct_ORS_request
from app.models.responses.bad_request import Bad_request
from app.models.responses.direct_ORS_response import Direct_ORS_response
from app.services.execute_direct_ors_request import Execute_direct_ors_request

direct_ors_request_bp = Blueprint("direct_ors_request", __name__)


@direct_ors_request_bp.route("/direct-ors-request", methods=["GET"])
def execute_direct_request() -> Response:
    try:
        req: Direct_ORS_request = Direct_ORS_request(**request.get_json())

        response: Direct_ORS_response = Execute_direct_ors_request.execute_request(req)

        return jsonify(response.to_dict()), response.get_ret_code()

    except Exception as e:
        return jsonify(Bad_request.to_dict()), 400
