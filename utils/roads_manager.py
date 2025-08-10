from typing import List, Tuple
from utils.database import get_db_connection
from openrouteservice import Client
from easy import Config, Logger
from time import sleep

import math

class Road_manager:
    """Singleton obj for  using open route service api and managing hashes for it"""
    _instance = None

    def __new__(cls, *args, **kwargs):
        if not cls._instance:
            cls._instance = super(Road_manager, cls).__new__(cls)

        return cls._instance

    def __init__(self, config: Config = None, logger: Logger = None):
        if not hasattr(self, 'initialized'):
            self.initialized: bool = True

            if config is None:
                raise TypeError("Parameter \"config\" must be initialized!")

            self.config: Config = config
            self.logger: Logger = logger if logger else config.logger
            self._set_api_clients()

    def _set_api_clients(self) -> None:
            self.api_client_index: int = 0
            self.clients: List[Client] = []

            for api_key in self.config.getValue("open route", "API keys"):
                self.clients.append(Client(key=api_key, retry_over_query_limit=False))

    def addClient(self, latitude: float, longitude: float) -> None:
        """In the absence of such parameters as latitude and longitude for the client,
        it adds them to the hash database table to avoid unnecessary requests to the server."""
        conn = get_db_connection()

        lat_min, lat_max, lon_min, lon_max = self._calculate_bounds(latitude, longitude)

        distances = conn.execute("""
            SELECT * FROM distance
            WHERE latitude_patient BETWEEN ? AND ?
            AND longitude_patient BETWEEN ? AND ?
        """, (lat_min, lat_max, lon_min, lon_max)).fetchall()

        if not distances:
            organizations = conn.execute("SELECT latitude, longitude FROM adosky", ()).fetchall()
            for organization in organizations:
                distance, pathTime = self.calculate_travel_time_and_distance((organization["latitude"], organization["longitude"]),
                    (latitude, longitude)
                )

                conn.execute("""
                    INSERT INTO distance
                    (latitude_compani, longitude_compani, latitude_patient, longitude_patient, distance, time)
                    VALUES(?, ?, ?, ?, ?, ?)""",
                    (
                        organization["latitude"], organization["longitude"], latitude, longitude, distance, pathTime

                    ))
                conn.commit()

        conn.close()

    def calculate_travel_time_and_distance(self, start: Tuple[float, float], end: Tuple[float, float]) -> Tuple[float, int]:
        """
        Returns a tuple containing the distance between two points in meters and the time required for the journey in seconds.

        @param start: A tuple representing the starting point's coordinates (latitude, longitude).
        @param end: A tuple representing the ending point's coordinates (latitude, longitude).
        @return: A tuple where the first element is the distance in meters and the second element is the time in seconds.
        Returns (None, None) if an error occurs while fetching the path time.
        """
        while True:
            backup_api_client_index: int = self.api_client_index

            try:
                routes = self.clients[self.api_client_index].directions( # API request
                    coordinates=[[start[1], start[0]], [end[1], end[0]]],
                    profile='driving-car',
                    format='json'
                )

                return routes["routes"][0]["summary"]["distance"], int(routes['routes'][0]['summary']['duration'])

            except Exception as e:
                if e.message["error"] == "Rate Limit Exceeded":
                    prev_api_index: int = self.api_client_index
                    self._toggle_clients_index()

                    self.logger.inform(f"The request limit for API key number {prev_api_index} has been reached, switching to number {self.api_client_index}...")

                    if self.api_client_index == backup_api_client_index: # sleep because we created cycle
                        sleep(self.config.getValue("open route", "sleep time after unsuccessful polling of all API keys, in seconds"))

                else:
                    return None, None

    def _toggle_clients_index(self) -> None:
        self.api_client_index += 1

        if self.api_client_index >= len(self.clients):
            self.api_client_index = 0

    def _calculate_bounds(self, latitude: float, longitude: float) -> Tuple[float, float, float, float]:
        """Calculates the bounding coordinates based on a given latitude and longitude.

        This method computes the minimum and maximum latitude and longitude values
        that define a rectangular area around the specified coordinates. The size of
        the area is determined by a configurable distance in meters.

        The distance is divided by the approximate number of meters per degree of
        latitude and longitude to calculate the corresponding degree offsets.

        Parameters:
        latitude (float): The latitude of the center point in decimal degrees.
        longitude (float): The longitude of the center point in decimal degrees.

        Returns:
        tuple: A tuple containing the minimum latitude, maximum latitude,
            minimum longitude, and maximum longitude, respectively.
            The format is (min_latitude, max_latitude, min_longitude, max_longitude).
        """
        distance_meters = self.config.getValue("open route", "delta distance between coordinates for searching hashed paths, in meters")

        lat_degree = distance_meters / 111320
        lon_degree = distance_meters / (111320 * math.cos(math.radians(latitude)))

        return (latitude - lat_degree, latitude + lat_degree, longitude - lon_degree, longitude + lon_degree)
