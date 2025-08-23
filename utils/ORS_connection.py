from openrouteservice import Client
from easy.config import Config
from utils.method_request_queue_manager import Method_request_queue_manager
from utils.request_queue import Request_queue
from typing import Callable, Dict

class ORS_connection:
    def __init__(self, config: Config, api_index: int):
        self.client: Client = Client(key=config.getValue("open route",
                                                         "API keys")[api_index],
                                     retry_over_query_limit=False)

        self.methods_requests_queue: Dict[str, Method_request_queue_manager] = {}

        for method_name in config.getValue("open route", "methods"):
            self.methods_requests_queue[method_name] = Method_request_queue_manager([
                Request_queue(
                    count_of_access=config.getValue("open route", "access limits", method_name, "per day"),
                    window_length=86400, # seconds in 1 day
                    request_time=config.getValue("open route", "access limits", method_name, "request time"),
                ),
                Request_queue(
                    count_of_access=config.getValue("open route", "access limits", method_name, "per minute"),
                    window_length=60, # seconds in 1 minute
                    request_time=config.getValue("open route", "access limits", method_name, "request time"),
                )
            ])

    def get_sleep_time_for_operation(self, operation: str) -> int:
        return self.methods_requests_queue[operation].get_request_sleep_time()

    def register_operation_request(self, operation: str) -> None:
        self.methods_requests_queue[operation].register_request()

    def get_operation_method(self, operation: str) -> Callable[..., object]:
        method = getattr(self.client, operation)

        if not callable(method):
            raise ValueError(f"Function '{operation}' does not exist.")

        return method
