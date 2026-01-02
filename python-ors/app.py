from flask import Flask, jsonify
import requests

app = Flask(__name__)

ORS_URL = "http://ors:8082/ors/v2/health"

@app.route("/", methods=["GET"])
def health_check():
    try:
        r = requests.get(ORS_URL, timeout=5)
        r.raise_for_status()
        return jsonify(r.json()), r.status_code

    except requests.RequestException as e:
        return jsonify({"error": str(e)}), 500

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=1515)
