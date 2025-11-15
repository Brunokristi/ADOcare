import sqlite3
from utils.database import get_db_connection
from flask_login import UserMixin

class User(UserMixin):
    def __init__(self, id: int, username: str, password: str):
        self.id = id
        self.username = username
        self.password = password

    def get_id(self) -> int:
        return str(self.id)

def get_user_by_username(username: str) -> User:
    conn = get_db_connection()
    usedData = conn.execute("SELECT * FROM adosky WHERE identifikator = ?", (username,)).fetchone()
    conn.close()
    if isinstance(usedData, sqlite3.Row):
        return User(id       = int(usedData['id']),
                    username = usedData['identifikator'],
                    password = usedData['passwordHash'])
    return None
