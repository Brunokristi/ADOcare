class Nurse:
    def __init__(self, row):
        self.id = row['id']
        self.meno = row['meno']
        self.kod = row['kod']
        self.uvazok = row['uvazok']
        self.vozidlo = row['vozidlo']
        self.ados = row['ados']
        self.phone_number = row['phone_number']
