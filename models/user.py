from flask_login import UserMixin

class User(UserMixin):
    def __init__(self, id: int, username: str, password: str):
        self.id = id
        self.username = username
        self.password = password

    def get_id(self) -> int:
        return str(self.id)

# tmd pseudo database
USERS = {
    "admin": User(id=1, username="admin", password="admin123")
}

def get_user_by_username(username: str) -> User:
    return USERS.get(username)
