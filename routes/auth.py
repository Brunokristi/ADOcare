from flask import Blueprint, request, redirect, url_for, render_template, flash
from flask_login import login_user, logout_user, login_required
from models.user import get_user_by_username, User
from utils.database import get_db_connection
import hashlib

auth_bp = Blueprint("auth", __name__, url_prefix="/auth")

@auth_bp.route("/login", methods=["GET", "POST"])
def login():
    if request.method == "POST":
        username = request.form["username"]
        UncashedPassword = request.form["password"]
        user = get_user_by_username(username)
        if user and user.password == hashlib.sha256(UncashedPassword.encode()).hexdigest():
            login_user(user)
            return redirect(url_for("main.index"))
        else:
            flash("Invalid credentials")
    return render_template("auth.html")

@auth_bp.route("/logout")
@login_required
def logout():
    logout_user()
    return redirect(url_for("auth.login"))

def setup_login_manager(login_manager) -> None:
    @login_manager.user_loader
    def load_user(user_id: int) -> User:
        conn = get_db_connection()
        usedData = conn.execute("SELECT * FROM adosky WHERE id = ?", (user_id,)).fetchone()
        conn.close()
        if usedData:
            return User(id       = int(usedData['id']),
                        username = usedData['identifikator'],
                        password = usedData['passwordHash'])
        return None
