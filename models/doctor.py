class Doctor:
    def __init__(self, row):
        self.id = row['id']
        self.meno = row['meno']
        self.pzs = row['pzs']
        self.zpr = row['zpr']
