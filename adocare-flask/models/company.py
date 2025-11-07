class Company:
    def __init__(self, row):
        self.id = row['id']
        self.nazov = row['nazov']
        self.ico = row['ico']
        self.ulica = row['ulica']
        self.mesto = row['mesto']
        self.identifikator = row['identifikator']
        self.kod = row['kod']
