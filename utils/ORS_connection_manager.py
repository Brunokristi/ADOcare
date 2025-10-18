from typing import Callable, List, Tuple
from easy.config import Config
from utils.ORS_connection import ORS_connection
from time import sleep

class ORS_connection_manager:
    def __init__(self, config: Config):
        self.ors_connections: List[ORS_connection] = []

        data = config._raw
        for i in range(len(data["open route"]["API keys"])):
            self.ors_connections.append(ORS_connection(config=config, api_index=i))

    def get_operation_method(self, operation: str) -> Callable[..., object]:
        best_variant: Tuple[int, int] = (None, None)

        for i in range(len(self.ors_connections)):
            sleep_time = self.ors_connections[i].get_sleep_time_for_operation(operation)
            if sleep_time == 0:
                self.ors_connections[i].register_operation_request(operation)

                return self.ors_connections[i].get_operation_method(operation)

            if best_variant[0] is None or best_variant[0] > sleep_time:
                best_variant = (sleep_time, i)

        sleep(best_variant[0])

        return self.ors_connections[best_variant[1]].get_operation_method(operation)
