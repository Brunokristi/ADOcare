class Macro:
    def __init__(self, row):
        self.id = row['id']
        self.nazov = row['nazov']
        self.text = row['text']
        self.skratka = row['skratka']
        self.farba = row['farba']
