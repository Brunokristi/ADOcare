# routes/dev.py
from flask import Blueprint, render_template

dev = Blueprint("dev", __name__)


@dev.route("/buttons")
def buttons_gallery():
    return render_template("dev/button_gallery.html")
