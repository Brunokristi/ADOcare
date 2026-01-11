from flask import Flask, Response, request, jsonify, Blueprint
from app.models.responses.bad_request import Bad_request
from app.utils.tsp_solver import tsp_solver

tsp_solver_bp = Blueprint("tsp_solver", __name__)


@tsp_solver_bp.route("/tsp-solver", methods=["GET"])
def tsp_solve() -> Response:
    try:
        req = request.get_json()
        try:
            return jsonify(
                {
                    "request" : req,
                    "response" : tsp_solver().calculate_part(**req)
                }
            ), 200

        except TypeError as e:
            return jsonify(
                {
                    "request" : req,
                    "response" : tsp_solver().calculate_path_with_time_management(**req)
                }
            ), 200

    except Exception as e:
        return jsonify(
            {
                **Bad_request.to_dict(),
                **{'message': f"{e}"}
            }), 400
