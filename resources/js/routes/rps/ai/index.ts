import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import week from './week'
/**
* @see \App\Http\Controllers\RpsAiController::generate
 * @see app/Http/Controllers/RpsAiController.php:17
 * @route '/rps/{rps}/ai/suggestions'
 */
export const generate = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generate.url(args, options),
    method: 'post',
})

generate.definition = {
    methods: ["post"],
    url: '/rps/{rps}/ai/suggestions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsAiController::generate
 * @see app/Http/Controllers/RpsAiController.php:17
 * @route '/rps/{rps}/ai/suggestions'
 */
generate.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return generate.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAiController::generate
 * @see app/Http/Controllers/RpsAiController.php:17
 * @route '/rps/{rps}/ai/suggestions'
 */
generate.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generate.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsAiController::generate
 * @see app/Http/Controllers/RpsAiController.php:17
 * @route '/rps/{rps}/ai/suggestions'
 */
    const generateForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: generate.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAiController::generate
 * @see app/Http/Controllers/RpsAiController.php:17
 * @route '/rps/{rps}/ai/suggestions'
 */
        generateForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: generate.url(args, options),
            method: 'post',
        })
    
    generate.form = generateForm
/**
* @see \App\Http\Controllers\RpsAiController::apply
 * @see app/Http/Controllers/RpsAiController.php:381
 * @route '/rps/{rps}/ai/suggestions/{suggestion}/apply'
 */
export const apply = (args: { rps: string | number, suggestion: string | number } | [rps: string | number, suggestion: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: apply.url(args, options),
    method: 'post',
})

apply.definition = {
    methods: ["post"],
    url: '/rps/{rps}/ai/suggestions/{suggestion}/apply',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsAiController::apply
 * @see app/Http/Controllers/RpsAiController.php:381
 * @route '/rps/{rps}/ai/suggestions/{suggestion}/apply'
 */
apply.url = (args: { rps: string | number, suggestion: string | number } | [rps: string | number, suggestion: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    suggestion: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                suggestion: args.suggestion,
                }

    return apply.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{suggestion}', parsedArgs.suggestion.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAiController::apply
 * @see app/Http/Controllers/RpsAiController.php:381
 * @route '/rps/{rps}/ai/suggestions/{suggestion}/apply'
 */
apply.post = (args: { rps: string | number, suggestion: string | number } | [rps: string | number, suggestion: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: apply.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsAiController::apply
 * @see app/Http/Controllers/RpsAiController.php:381
 * @route '/rps/{rps}/ai/suggestions/{suggestion}/apply'
 */
    const applyForm = (args: { rps: string | number, suggestion: string | number } | [rps: string | number, suggestion: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: apply.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAiController::apply
 * @see app/Http/Controllers/RpsAiController.php:381
 * @route '/rps/{rps}/ai/suggestions/{suggestion}/apply'
 */
        applyForm.post = (args: { rps: string | number, suggestion: string | number } | [rps: string | number, suggestion: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: apply.url(args, options),
            method: 'post',
        })
    
    apply.form = applyForm
/**
* @see \App\Http\Controllers\RpsAiController::reject
 * @see app/Http/Controllers/RpsAiController.php:482
 * @route '/rps/{rps}/ai/suggestions/{suggestion}/reject'
 */
export const reject = (args: { rps: string | number, suggestion: string | number } | [rps: string | number, suggestion: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reject.url(args, options),
    method: 'post',
})

reject.definition = {
    methods: ["post"],
    url: '/rps/{rps}/ai/suggestions/{suggestion}/reject',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsAiController::reject
 * @see app/Http/Controllers/RpsAiController.php:482
 * @route '/rps/{rps}/ai/suggestions/{suggestion}/reject'
 */
reject.url = (args: { rps: string | number, suggestion: string | number } | [rps: string | number, suggestion: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    suggestion: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                suggestion: args.suggestion,
                }

    return reject.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{suggestion}', parsedArgs.suggestion.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAiController::reject
 * @see app/Http/Controllers/RpsAiController.php:482
 * @route '/rps/{rps}/ai/suggestions/{suggestion}/reject'
 */
reject.post = (args: { rps: string | number, suggestion: string | number } | [rps: string | number, suggestion: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reject.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsAiController::reject
 * @see app/Http/Controllers/RpsAiController.php:482
 * @route '/rps/{rps}/ai/suggestions/{suggestion}/reject'
 */
    const rejectForm = (args: { rps: string | number, suggestion: string | number } | [rps: string | number, suggestion: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reject.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAiController::reject
 * @see app/Http/Controllers/RpsAiController.php:482
 * @route '/rps/{rps}/ai/suggestions/{suggestion}/reject'
 */
        rejectForm.post = (args: { rps: string | number, suggestion: string | number } | [rps: string | number, suggestion: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reject.url(args, options),
            method: 'post',
        })
    
    reject.form = rejectForm
const ai = {
    generate: Object.assign(generate, generate),
week: Object.assign(week, week),
apply: Object.assign(apply, apply),
reject: Object.assign(reject, reject),
}

export default ai