class Patient:
    def __init__(self, row):
        self.id = row['id']
        self.meno = row['meno']
        self.rodne_cislo = row['rodne_cislo']
        self.adresa = row['adresa']
        self.poistovna = row['poistovna']
        self.ados = row['ados']
        self.sestra = row['sestra']
        self.odosielatel = row['odosielatel']
        self.pohlavie = row['pohlavie']
        self.cislo_dekurzu = row['cislo_dekurzu']
        self.last_month = row['last_month']