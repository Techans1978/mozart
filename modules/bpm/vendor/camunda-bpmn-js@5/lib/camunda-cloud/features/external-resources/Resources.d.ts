export class Resources {
  static $inject: string[];
  constructor(resources: any);
  set(resources: any): void;
  getAll(): any;
  filter(fn: any): any;
}
