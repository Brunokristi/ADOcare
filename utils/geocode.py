from geopy.geocoders import Nominatim
from time import sleep

geolocator = Nominatim(user_agent="geoapi")

def geocode_address(address):
    try:
        location = geolocator.geocode(address)
        if location:
            return location.longitude, location.latitude
    except:
        pass
    return None, None
