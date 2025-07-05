from flask_login import login_user, logout_user, login_required, current_user
from models.user import get_user_by_username, User
from flask import request, redirect, url_for, Blueprint, render_template
from flask_login import LoginManager

def setup_login_manager(login_manager):
    from models.user import USERS

    @login_manager.user_loader
    def load_user(user_id: int) -> User: # It will be changed to the actual functionality of working with the database.
        for user in USERS.values():
            if str(user.id) == str(user_id):
                return user
        return None
