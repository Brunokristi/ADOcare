import sqlite3
import os
from calendar import monthrange
from datetime import date
from time import sleep

DATABASE_FILE = "ados_database.db"

def get_db_connection():
    conn = sqlite3.connect(DATABASE_FILE)
    conn.row_factory = sqlite3.Row
    return conn
