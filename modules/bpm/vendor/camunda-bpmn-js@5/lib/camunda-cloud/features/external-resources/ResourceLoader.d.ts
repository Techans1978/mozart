export class ResourceLoader {
  static $inject: string[];
  constructor(eventBus: any, resources: any);
  register(resourceProvider: any): void;
  reload(): void;
}
