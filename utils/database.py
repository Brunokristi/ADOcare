import sqlite3
import os

DATABASE_FILE = "ados_database.db"

def get_db_connection():
    conn = sqlite3.connect(DATABASE_FILE)
    conn.row_factory = sqlite3.Row
    return conn

def check_db():
    if not os.path.exists(DATABASE_FILE):
        initialize_db()
    else:
        update_db()

def initialize_db():
    """Creates the database from scratch."""
    with sqlite3.connect(DATABASE_FILE) as conn:
        cursor = conn.cursor()
        cursor.executescript("""
            CREATE TABLE sestry (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                meno TEXT NOT NULL,
                kod TEXT,
                uvazok DECIMAL(2,1),
                vozidlo INTEGER,
                ados INTEGER,
                FOREIGN KEY (vozidlo) REFERENCES auta(id) ON DELETE SET NULL,
                FOREIGN KEY (ados) REFERENCES adosky(id) ON DELETE SET NULL
            );
                             
            CREATE TABLE auta (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                evc TEXT NOT NULL
            );

            CREATE TABLE adosky (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nazov TEXT NOT NULL,
                ico TEXT NOT NULL,
                ulica TEXT,
                mesto TEXT,
                identifikator TEXT,
                kod TEXT
            );

            CREATE TABLE mesiac (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                mesiac INTEGER NOT NULL,
                rok INTEGER NOT NULL,
                vysetrenie_start TIME NOT NULL,
                vysetrenie_koniec TIME NOT NULL,
                vypis_start TIME NOT NULL,
                vypis_koniec TIME NOT NULL,
                sestra_id INTEGER NOT NULL,
                FOREIGN KEY (sestra_id) REFERENCES sestry(id) ON DELETE CASCADE
            );

            CREATE TABLE dni (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                mesiac INTEGER NOT NULL,
                datum DATE NOT NULL,
                FOREIGN KEY (mesiac) REFERENCES mesiac(id) ON DELETE CASCADE
            );

            CREATE TABLE pacienti (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                meno TEXT NOT NULL,
                rodne_cislo UNIQUE NOT NULL,
                adresa TEXT NOT NULL,
                poistovna TEXT NOT NULL,
                ados TEXT NOT NULL,
                sestra INTEGER NOT NULL,
                nalez TEXT,
                osetrenie TEXT,
                vedlajsie_osetrenie TEXT,
                poznamka1 TEXT,
                poznamka2 TEXT,
                koniec_mesiaca TEXT,
                cislo_dekurzu INTEGER,
                vypisane BOOLEAN DEFAULT 0,
                -- New structured columns for text entries
                dekurz_text_0 TEXT,
                dekurz_text_1 TEXT,
                dekurz_text_2 TEXT,
                dekurz_text_3 TEXT,
                dekurz_text_4 TEXT,
                dekurz_text_5 TEXT,
                dekurz_text_6 TEXT,
                dekurz_text_7 TEXT,
                dekurz_text_8 TEXT,
                FOREIGN KEY (sestra) REFERENCES sestry(id) ON DELETE SET NULL
            );

            CREATE TABLE den_pacient (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                den_id INTEGER NOT NULL,
                pacient_id INTEGER,
                vysetrenie DATETIME,
                vypis DATETIME,
                poradie_pacienta INTEGER,
                FOREIGN KEY (den_id) REFERENCES dni(id) ON DELETE CASCADE,
                FOREIGN KEY (pacient_id) REFERENCES pacienti(id) ON DELETE CASCADE
            );
                             
            CREATE TABLE makra (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nazov VARCHAR(100) NOT NULL,
                text LONGTEXT NOT NULL,
                skratka VARCHAR(100),
                farba VARCHAR(100)
            );
        """)



def get_existing_tables(cursor):
    cursor.execute("SELECT name FROM sqlite_master WHERE type='table'")
    return {row[0] for row in cursor.fetchall()}

def get_column_names(cursor, table_name):
    cursor.execute(f"PRAGMA table_info({table_name})")
    return [row["name"] for row in cursor.fetchall()]



def create_auta(cursor, existing_tables):
    if "auta" not in existing_tables:
        cursor.executescript("""
            CREATE TABLE IF NOT EXISTS auta (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                evc TEXT NOT NULL
            );
        """)
        
        cursor.executemany(
            "INSERT INTO auta (evc) VALUES (?)",
            [("LC505BC",), ("LC988CJ",)]
        )

def create_adosky(cursor, existing_tables):
    if "adosky" not in existing_tables:
        cursor.executescript("""
            CREATE TABLE IF NOT EXISTS adosky (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nazov TEXT NOT NULL,
                ico TEXT NOT NULL,
                ulica TEXT,
                mesto TEXT,
                identifikator TEXT,
                kod TEXT
            );
        """)

        cursor.execute(
            """
            INSERT INTO adosky (nazov, ico, ulica, mesto, identifikator, kod)
            VALUES (?, ?, ?, ?, ?, ?)
            """,
            (
                "Andramed, o.z.",
                "42300771",
                "SNP 8",
                "Fiľakovo",
                "P78688",
                "P78688610301"
            )
        )

def create_makra(cursor, existing_tables):
    if "makra" not in existing_tables:
        cursor.execute("""
            CREATE TABLE makra (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nazov TEXT NOT NULL,
                text LONGTEXT NOT NULL,
                skratka TEXT,
                farba TEXT
            );
        """)

def create_doktori(cursor, existing_tables):
    if "doktori" not in existing_tables:
        cursor.executescript("""
            CREATE TABLE IF NOT EXISTS doktori (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                meno TEXT NOT NULL,
                pzs TEXT,
                zpr TEXT
            );
        """)

        # Seed with initial data
        cursor.executemany(
            """
            INSERT INTO doktori (meno, pzs, zpr)
            VALUES (?, ?, ?)
            """,
            [
                ("MUDr. Norbert Kristán", "P86135020203", "A81036020"),
                ("MUDr. Martina Kristiánová", "P86135020204", "A75377020"),
                ("MUDr. Krisztiánová Eva", "P86135020202", "A39340020")
            ]
        )

def create_diagnozy(cursor, existing_tables, file_path="utils/diagnozy.txt"):
    if "diagnozy" not in existing_tables:
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS diagnozy (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nazov TEXT NOT NULL,
                kod TEXT NOT NULL
            );
        """)

        with open(file_path, "r", encoding="utf-8") as f:
            lines = f.readlines()
            data = []
            for line in lines:
                line = line.strip()
                if not line or "," not in line:
                    continue
                parts = line.split(",", 1)
                if len(parts) == 2:
                    kod, nazov = parts[0].strip(), parts[1].strip()
                    data.append((nazov, kod))  # flip to match DB schema

        print(data)  # Optional: for debugging

        cursor.executemany(
            "INSERT INTO diagnozy (nazov, kod) VALUES (?, ?)",
            data
        )

def create_mesiac_pacient(cursor, existing_tables):
    if "mesiac_pacient" not in existing_tables:
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS mesiac_pacient (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                mesiac_id INTEGER,
                pacient_id INTEGER,
                podtext0 TEXT,
                dates0 TEXT,
                podtext1 TEXT,
                dates1 TEXT,
                podtext2 TEXT,
                dates2 TEXT,
                podtext3 TEXT,
                dates3 TEXT,
                podtext4 TEXT,
                dates4 TEXT,
                podtext5 TEXT,
                dates5 TEXT,
                podtext6 TEXT,
                dates6 TEXT,
                podtext7 TEXT,
                dates7 TEXT,
                dates_all TEXT
            );
        """)

def create_pacienti(cursor, existing_tables):
    if "pacienti" not in existing_tables:
        cursor.execute("""
        CREATE TABLE pacienti (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            meno TEXT NOT NULL,
            rodne_cislo INTEGER,
            adresa TEXT,
            poistovna TEXT,
            ados TEXT,
            sestra INTEGER,
            odosielatel INTEGER,
            pohlavie CHAR,
            cislo_dekurzu INTEGER,
            last_month INTEGER,
            FOREIGN KEY (sestra) REFERENCES sestry(id) ON DELETE SET NULL,
            FOREIGN KEY (last_month) REFERENCES mesiace(id) ON DELETE SET NULL,
            FOREIGN KEY (odosielatel) REFERENCES doktori(id) ON DELETE SET NULL
        );
    """)

def reconstruct_sestry(cursor):
    cursor.execute("PRAGMA table_info(sestry)")
    existing_columns = {row[1] for row in cursor.fetchall()}

    required_columns = {"kod", "uvazok", "vozidlo", "ados"}

    if required_columns.issubset(existing_columns):
        return


    cursor.execute("ALTER TABLE sestry RENAME TO sestry_old;")

    cursor.execute("""
        CREATE TABLE sestry (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            meno TEXT NOT NULL,
            kod TEXT,
            uvazok DECIMAL(2,1),
            vozidlo INTEGER,
            ados INTEGER,
            FOREIGN KEY (vozidlo) REFERENCES auta(id) ON DELETE SET NULL,
            FOREIGN KEY (ados) REFERENCES adosky(id) ON DELETE SET NULL
        );
    """)

    cursor.execute("PRAGMA table_info(sestry_old)")
    old_columns = {row[1] for row in cursor.fetchall()}

    if required_columns.issubset(old_columns):
        cursor.execute("""
            INSERT INTO sestry (id, meno, kod, uvazok, vozidlo, ados)
            SELECT id, meno, kod, uvazok, CAST(vozidlo AS INTEGER), ados FROM sestry_old;
        """)
    else:
        cursor.execute("""
            INSERT INTO sestry (id, meno)
            SELECT id, meno FROM sestry_old;
        """)

    cursor.execute("DROP TABLE sestry_old;")

def migrate_to_mesiac_pacient(cursor):
    columns = get_column_names(cursor, "pacienti")

    if any(col.startswith("dekurz_text_") for col in columns):
        cursor.execute("""
            SELECT id,
                   dekurz_text_0, dekurz_text_1, dekurz_text_2, dekurz_text_3,
                   dekurz_text_4, dekurz_text_5, dekurz_text_6, dekurz_text_7
            FROM pacienti
        """)
        rows = cursor.fetchall()

        for row in rows:
            cursor.execute("""
                INSERT INTO mesiac_pacient (
                    pacient_id, mesiac_id,
                    podtext0, podtext1, podtext2, podtext3,
                    podtext4, podtext5, podtext6, podtext7
                )
                VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                row["id"],
                row["dekurz_text_0"], row["dekurz_text_1"],
                row["dekurz_text_2"], row["dekurz_text_3"],
                row["dekurz_text_4"], row["dekurz_text_5"],
                row["dekurz_text_6"], row["dekurz_text_7"]
            ))

        print("Migrated dekurz_text_* data to mesiac_pacient.")

def reconstruct_pacienti(cursor):
    desired_columns = [
        "id", "meno", "rodne_cislo", "adresa", "poistovna", "ados",
        "sestra", "odosielatel", "pohlavie", "cislo_dekurzu", "last_month"
    ]

    current_columns = get_column_names(cursor, "pacienti")

    if set(current_columns) != set(desired_columns):
        print("Reconstructing pacienti table to match updated schema...")

        # Rename old table
        cursor.execute("ALTER TABLE pacienti RENAME TO pacienti_old")

        # Create new table with correct structure and foreign keys
        cursor.execute("""
            CREATE TABLE pacienti (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                meno TEXT NOT NULL,
                rodne_cislo INTEGER,
                adresa TEXT,
                poistovna TEXT,
                ados TEXT,
                sestra INTEGER,
                odosielatel INTEGER,
                pohlavie CHAR,
                cislo_dekurzu INTEGER,
                last_month INTEGER,
                FOREIGN KEY (sestra) REFERENCES sestry(id) ON DELETE SET NULL,
                FOREIGN KEY (last_month) REFERENCES mesiace(id) ON DELETE SET NULL,
                FOREIGN KEY (odosielatel) REFERENCES doktori(id) ON DELETE SET NULL
            );
        """)

        # Copy over data that matches new structure
        copy_columns = [col for col in desired_columns if col in current_columns]
        column_list = ", ".join(copy_columns)

        cursor.execute(f"""
            INSERT INTO pacienti ({column_list})
            SELECT {column_list}
            FROM pacienti_old;
        """)

        # Drop old table
        cursor.execute("DROP TABLE pacienti_old")

        print("Pacienti table successfully reconstructed with full foreign keys.")
    else:
        print("Pacienti table already matches desired schema.")

def update_db():
    with sqlite3.connect(DATABASE_FILE) as conn:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        existing_tables = get_existing_tables(cursor)

        create_makra(cursor, existing_tables)
        create_auta(cursor, existing_tables)
        create_adosky(cursor, existing_tables)
        create_doktori(cursor, existing_tables)
        create_diagnozy(cursor, existing_tables)
        create_mesiac_pacient(cursor, existing_tables)
        create_pacienti(cursor, existing_tables)
        reconstruct_sestry(cursor)
        migrate_to_mesiac_pacient(cursor)
        reconstruct_pacienti(cursor)

        conn.commit()



