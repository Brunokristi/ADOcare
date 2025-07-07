from flask import Blueprint, render_template

from flask_login import login_required

documents_bp = Blueprint("documents", __name__)

@documents_bp.route('/documents/navrh', methods=['GET', 'POST'])
@login_required
def showNavrh():
    return render_template("documents/navrh.html")

@documents_bp.route('/documents/zaznam', methods=['GET', 'POST'])
@login_required
def showZaznam():
    return render_template("documents/zaznam.html")

@documents_bp.route('/documents/dohoda', methods=['GET', 'POST'])
@login_required
def showDohoda():
    return render_template("documents/dohoda.html")
