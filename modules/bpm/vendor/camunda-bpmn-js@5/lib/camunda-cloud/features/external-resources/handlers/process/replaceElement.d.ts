/**
 *
 * @param element
 * @param resource
 * @param injector
 */
export function replaceElement(
  element: import("diagram-js/lib/model").Element,
  resource: CallActivity,
  injector: import("didi").Injector
): void;
export type CallActivity = {
    type: "bpmnProcess";
    name: string;
    processId: string;
};
