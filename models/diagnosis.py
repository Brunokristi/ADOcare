class Diagnosis:
    def __init__(self, row):
        self.id = row['id']
        self.kod = row['kod']
        self.nazov = row['nazov']
