/**
 * Command handler to compose multiple commands into a single command.
 * It allows to use facades like `BpmnReplace` and `Modeling` while retaining
 * single undo/redo feature.
 */
export class ComposedCommandHandler {
  constructor(commandStack: any);
  preExecute(context: any): void;
}
export namespace ComposedCommandHandler {
    let $inject: string[];
}
